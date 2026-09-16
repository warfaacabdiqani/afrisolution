<?php

namespace Tests\Feature;

use App\Models\{Plan, User};
use App\Services\ClinicSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClinicBillingTest extends TestCase
{
    use RefreshDatabase;
    private const ROOT = '/api/v1/clinic/appointments/';

    private function fixture(string $name = 'clinic-billing', string $business = 'clinic'): array
    {
        require_once base_path('tests/Support/clinic-billing-fixture.php');
        $f = clinicBillingFixture($name, $business);
        $this->login($f);
        return $f;
    }

    private function login(array $f): void
    {
        $this->flushSession(); $this->app['auth']->forgetGuards();
        $this->withHeader('Origin', 'http://localhost')->actingAs(User::find($f['user']))
            ->postJson('/api/v1/session/clinic', ['clinic_id' => $f['tenant']])->assertOk();
    }

    private function issue(array $f, array $payload = [])
    {
        return $this->postJson(self::ROOT.$f['appointment'].'/invoice', $payload);
    }

    private function anotherAppointment(array $f, array $changes = []): array
    {
        $row = (array) DB::table('appointments')->find($f['appointment']);
        unset($row['id']);
        $row['appointment_number'] = 'APT-'.(DB::table('appointments')->count() + 1);
        $f['appointment'] = DB::table('appointments')->insertGetId(array_replace($row, $changes));
        return $f;
    }

    private function permissions(array $f, array $permissions): void
    {
        DB::table('tenant_memberships')->where('tenant_id', $f['tenant'])->where('user_id', $f['user'])
            ->update(['permissions' => json_encode($permissions)]);
    }

    public function test_completed_consultation_creates_one_owned_invoice_in_shared_ledger(): void
    {
        $f = $this->fixture();
        $this->getJson(self::ROOT.$f['appointment'])->assertOk()->assertJsonPath('data.invoice_id', null);
        $invoice = $this->issue($f)->assertCreated()->assertJsonPath('data.customer.type', 'patient')
            ->assertJsonPath('data.customer.id', $f['patient'])->assertJsonPath('data.branch_id', $f['branch'])
            ->assertJsonPath('data.source.type', 'clinic_appointment')->assertJsonPath('data.source.id', $f['appointment'])
            ->assertJsonPath('data.items.0.description', 'Consultation - Dr. Ahmed Hassan')
            ->assertJsonPath('data.items.0.unit_price', '20.00')->assertJsonPath('data.total', '20.00')->json('data');
        $this->assertDatabaseHas('billing_invoices', ['id' => $invoice['id'], 'patient_id' => $f['patient'], 'salon_client_id' => null, 'tenant_id' => $f['tenant']]);
        $this->issue($f)->assertCreated()->assertJsonPath('data.id', $invoice['id']);
        $this->assertDatabaseCount('billing_invoices', 1);
        $this->getJson(self::ROOT.$f['appointment'])->assertJsonPath('data.invoice_id', $invoice['id']);
        $this->getJson('/api/v1/billing/invoices')->assertOk()->assertJsonPath('data.data.0.id', $invoice['id']);
        $audit = json_decode(DB::table('platform_audit_logs')->where('action', 'billing.invoice.created')->value('metadata'), true);
        $this->assertSame($f['patient'], $audit['patient_id']); $this->assertSame($f['branch'], $audit['branch_id']);
        $this->assertSame('clinic', $audit['business_type']); $this->assertArrayNotHasKey('notes', $audit);
    }

    public function test_only_completed_status_is_billable_and_completion_does_not_auto_invoice(): void
    {
        $f = $this->fixture();
        foreach (array_diff(array_keys(config('appointments.statuses')), ['completed']) as $status) {
            DB::table('appointments')->where('id', $f['appointment'])->update(['status' => $status]);
            $this->issue($f)->assertUnprocessable()->assertJsonValidationErrors('status');
        }
        DB::table('appointments')->where('id', $f['appointment'])->update(['status' => 'scheduled', 'completed_at' => null]);
        foreach (['check-in', 'start-consultation', 'complete'] as $action) $this->postJson(self::ROOT.$f['appointment'].'/'.$action)->assertOk();
        $this->assertDatabaseCount('billing_invoices', 0);
        $this->issue($f)->assertCreated();
    }

    public function test_doctor_fee_patient_name_currency_and_tax_are_immutable_snapshots(): void
    {
        $f = $this->fixture();
        app(ClinicSettingsService::class)->set($f['tenant'], 'billing', ['tax_rate' => 5, 'currency' => 'USD', 'consultation_fee' => 99], $f['user']);
        $first = $this->issue($f)->assertCreated()->assertJsonPath('data.total', '21.00')->assertJsonPath('data.tax_rate', '5.00')
            ->assertJsonPath('data.items.0.tax_amount', '1.00')->assertJsonPath('data.items.0.discount_amount', '0.00')->json('data');
        DB::table('doctors')->where('id', $f['doctor'])->update(['consultation_fee' => 30, 'first_name' => 'Changed']);
        DB::table('patients')->where('id', $f['patient'])->update(['first_name' => 'Changed']);
        app(ClinicSettingsService::class)->set($f['tenant'], 'billing', ['tax_rate' => 10, 'currency' => 'KES'], $f['user']);
        $this->assertSame($first, $this->issue($f)->assertCreated()->json('data'));
        $this->getJson('/api/v1/billing/invoices/'.$first['id'])->assertJsonPath('data.customer.name', 'Amina Yusuf')->assertJsonPath('data.currency', 'USD');
        $this->issue($this->anotherAppointment($f))->assertCreated()->assertJsonPath('data.items.0.unit_price', '30.00')
            ->assertJsonPath('data.total', '33.00')->assertJsonPath('data.currency', 'KES')->assertJsonPath('data.customer.name', 'Changed Yusuf');
    }

    public function test_default_fee_fallback_is_snapshotted_and_zero_doctor_fee_does_not_fallback(): void
    {
        $f = $this->fixture(); DB::table('doctors')->where('id', $f['doctor'])->update(['consultation_fee' => null]);
        app(ClinicSettingsService::class)->set($f['tenant'], 'billing', ['consultation_fee' => 15], $f['user']);
        $first = $this->issue($f)->assertCreated()->assertJsonPath('data.total', '15.00')->json('data.id');
        app(ClinicSettingsService::class)->set($f['tenant'], 'billing', ['consultation_fee' => 25], $f['user']);
        $this->issue($f)->assertJsonPath('data.total', '15.00');
        $this->issue($this->anotherAppointment($f))->assertJsonPath('data.total', '25.00');
        DB::table('doctors')->where('id', $f['doctor'])->update(['consultation_fee' => 0]);
        $this->issue($this->anotherAppointment($f))->assertCreated()->assertJsonPath('data.total', '0.00')->assertJsonPath('data.status', 'paid');
        $this->getJson('/api/v1/billing/invoices/'.$first)->assertJsonPath('data.total', '15.00');
    }

    public function test_source_access_billing_permissions_plan_and_capabilities_are_required(): void
    {
        $f = $this->fixture();
        foreach ([['billing.view', 'billing.create'], ['appointments.view', 'billing.create'], ['appointments.view', 'billing.view']] as $permissions) {
            $this->permissions($f, $permissions); $this->issue($f)->assertForbidden();
        }
        // An assigned clinician needs view + billing rights, not appointment-management rights.
        $this->permissions($f, ['appointments.view', 'billing.view', 'billing.create']);
        DB::table('doctors')->where('id', $f['doctor'])->update(['user_id' => null]);
        $this->issue($f)->assertNotFound();
        DB::table('doctors')->where('id', $f['doctor'])->update(['user_id' => $f['user']]);
        $plan = Plan::find($f['plan']); $features = $plan->features; $features['billing'] = false; $plan->update(['features' => $features]);
        $this->issue($f)->assertForbidden()->assertJsonPath('code', 'PLAN_FEATURE_UNAVAILABLE');
        $features['billing'] = true; $plan->update(['features' => $features]);
        config(['business_types.clinic.modules.billing' => false]); $this->issue($f)->assertForbidden()->assertJsonPath('code', 'BUSINESS_MODULE_UNAVAILABLE');
        config(['business_types.clinic.modules.billing' => true]);
        $this->issue($f)->assertCreated();
    }

    public function test_cross_tenant_appointments_patients_filters_and_salon_access_are_rejected(): void
    {
        $a = $this->fixture('clinic-a'); $this->issue($a)->assertCreated();
        $b = $this->fixture('clinic-b');
        $this->issue($a)->assertNotFound();
        $this->getJson('/api/v1/billing/invoices?customer_type=patient&customer_id='.$a['patient'])->assertNotFound();
        $this->issue($b, ['patient_id' => $a['patient']])->assertUnprocessable();
        $this->fixture('salon', 'beauty-salon'); $this->issue($a)->assertForbidden()->assertJsonPath('code', 'BUSINESS_MODULE_UNAVAILABLE');
        $this->getJson('/api/v1/billing/invoices?customer_type=patient&customer_id='.$a['patient'])->assertForbidden();
        $this->login($b);
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('appointments')->where('id', $b['appointment'])->update(['patient_id' => $a['patient']]);
    }

    public function test_branch_authorization_and_server_owned_inputs(): void
    {
        $f = $this->fixture();
        foreach (['patient_id' => 999, 'branch_id' => 999, 'tenant_id' => 999, 'items' => [['unit_price' => 1]], 'total' => 1, 'source_type' => 'salon_appointment'] as $key => $value) {
            $this->issue($f, [$key => $value])->assertUnprocessable();
        }
        $branch = DB::table('branches')->insertGetId(['tenant_id' => $f['tenant'], 'name' => 'Authorized only', 'status' => 'active']);
        $member = DB::table('tenant_memberships')->where('tenant_id', $f['tenant'])->first();
        DB::table('tenant_memberships')->where('id', $member->id)->update(['all_branches' => false]);
        DB::table('branch_memberships')->insert(['tenant_id' => $f['tenant'], 'membership_id' => $member->id, 'branch_id' => $branch]);
        $this->issue($f)->assertNotFound();
        $this->assertDatabaseCount('billing_invoices', 0);
    }

    public function test_patient_filter_returns_only_owned_patient_invoices_with_branch_scope(): void
    {
        $f = $this->fixture(); $id = $this->issue($f)->assertCreated()->json('data.id');
        $patient = (array) DB::table('patients')->find($f['patient']); unset($patient['id']); $patient['patient_number'] = 'PAT-2';
        $otherPatient = DB::table('patients')->insertGetId($patient);
        $this->issue($this->anotherAppointment($f, ['patient_id' => $otherPatient]))->assertCreated();
        $url = '/api/v1/billing/invoices?customer_type=patient&customer_id='.$f['patient'];
        $this->getJson($url)->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.id', $id);
        $this->getJson('/api/v1/billing/invoices?customer_id='.$f['patient'])->assertUnprocessable();
        $this->getJson('/api/v1/billing/invoices?customer_type=App\\Models\\Patient&customer_id=1')->assertUnprocessable();
        $branch = DB::table('branches')->insertGetId(['tenant_id' => $f['tenant'], 'name' => 'Other branch', 'status' => 'active']);
        // Patients are shared across treatment branches; invoice ownership is the appointment branch.
        $this->issue($this->anotherAppointment($f, ['branch_id' => $branch]))->assertCreated()->assertJsonPath('data.branch_id', $branch);
        $member = DB::table('tenant_memberships')->where('tenant_id', $f['tenant'])->first();
        DB::table('tenant_memberships')->where('id', $member->id)->update(['all_branches' => false]);
        DB::table('branch_memberships')->insert(['tenant_id' => $f['tenant'], 'membership_id' => $member->id, 'branch_id' => $f['branch']]);
        $this->getJson($url)->assertOk()->assertJsonCount(1, 'data.data')->assertJsonPath('data.data.0.id', $id);
    }

    public function test_clinic_invoice_uses_shared_partial_full_idempotent_payments(): void
    {
        $f = $this->fixture(); $id = $this->issue($f)->assertCreated()->json('data.id');
        $url = '/api/v1/billing/invoices/'.$id.'/payments';
        $first = ['amount' => 5, 'method' => 'cash', 'idempotency_key' => 'clinic-partial'];
        $this->postJson($url, $first)->assertOk()->assertJsonPath('data.status', 'partial')->assertJsonPath('data.balance', '15.00');
        $this->postJson($url, $first)->assertOk()->assertJsonCount(1, 'data.payments');
        $this->postJson($url, ['amount' => 16, 'method' => 'cash', 'idempotency_key' => 'too-much'])->assertUnprocessable();
        $this->postJson($url, ['amount' => 15, 'method' => 'card', 'idempotency_key' => 'disabled'])->assertUnprocessable();
        $this->postJson($url, ['amount' => 15, 'method' => 'cash', 'idempotency_key' => 'clinic-full'])->assertOk()->assertJsonPath('data.status', 'paid')->assertJsonPath('data.balance', '0.00');
    }

    public function test_monthly_limit_is_shared_and_retry_does_not_consume_another_number(): void
    {
        $f = $this->fixture(); Plan::find($f['plan'])->update(['invoice_limit' => 1]);
        $id = $this->issue($f)->assertCreated()->json('data.id');
        $this->issue($f)->assertCreated()->assertJsonPath('data.id', $id);
        $this->issue($this->anotherAppointment($f))->assertUnprocessable()->assertJsonValidationErrors('plan');
        $this->assertSame(1, (int) DB::table('tenants')->where('id', $f['tenant'])->value('billing_invoice_sequence'));
    }
}

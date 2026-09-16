<?php

namespace Tests\Feature;

use App\Models\{BusinessType, Plan, Tenant, User};
use App\Services\{ClinicSettingsService, PlatformService};
use App\Services\Billing\BillingCustomerResolver;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SharedBillingTest extends TestCase
{
    use RefreshDatabase;

    private function salon(): array
    {
        require_once base_path('tests/Support/salon-booking-fixture.php');
        $fixture = salonBookingFixture();
        $this->login($fixture);
        return $fixture;
    }

    private function login(array $fixture): void
    {
        $this->flushSession(); $this->app['auth']->forgetGuards();
        $this->withHeader('Origin', 'http://localhost')->actingAs(User::findOrFail($fixture['user']))
            ->postJson('/api/v1/session/clinic', ['clinic_id' => $fixture['tenant']])->assertOk();
    }

    private function other(string $type): array
    {
        $actor = User::factory()->create();
        $plan = Plan::firstOrFail();
        $name = $type.'-'.User::count();
        $tenant = app(PlatformService::class)->createTenant(['name' => $name, 'timezone' => 'Africa/Nairobi',
            'business_type_id' => BusinessType::where('slug', $type)->value('id'), 'plan_id' => $plan->id,
            'owner_name' => 'Other', 'owner_email' => $name.'@example.test', 'owner_password' => 'SecurePass12345'], $actor->id);
        return ['tenant' => $tenant->id, 'user' => User::where('email', $name.'@example.test')->value('id'),
            'branch' => DB::table('branches')->where('tenant_id', $tenant->id)->value('id')];
    }

    private function appointment(array $f, string $number = 'APT-TEST', string $discount = '0.00', string $rate = '0.00'): int
    {
        $tax = round((45 - (float) $discount) * (float) $rate / 100, 2);
        $id = DB::table('salon_appointments')->insertGetId(['tenant_id' => $f['tenant'], 'branch_id' => $f['branch'],
            'client_id' => $f['client'], 'stylist_id' => $f['stylist'], 'appointment_number' => $number,
            'starts_at' => now(), 'ends_at' => now()->addMinutes(90), 'status' => 'completed', 'source' => 'reception',
            'currency' => 'USD', 'duration_minutes' => 90, 'subtotal' => 45, 'discount' => $discount, 'tax_rate' => $rate,
            'tax' => $tax, 'total' => 45 - (float) $discount + $tax, 'created_by' => $f['user'], 'updated_by' => $f['user'],
            'created_at' => now(), 'updated_at' => now()]);
        foreach ($f['services'] as $service) {
            $s = DB::table('salon_services')->find($service);
            DB::table('salon_appointment_services')->insert(['tenant_id' => $f['tenant'], 'appointment_id' => $id,
                'service_id' => $service, 'name' => $s->name, 'duration_minutes' => $s->duration_minutes, 'unit_price' => $s->price,
                'amount' => $s->price, 'created_at' => now(), 'updated_at' => now()]);
        }
        return $id;
    }

    private function issue(int $appointment)
    {
        return $this->postJson('/api/v1/salon/appointments/'.$appointment.'/invoice');
    }

    private function permissions(array $f, array $permissions): void
    {
        DB::table('tenant_memberships')->where('tenant_id', $f['tenant'])->where('user_id', $f['user'])
            ->update(['permissions' => json_encode($permissions)]);
    }

    public function test_owned_snapshots_reconcile_and_survive_customer_and_catalog_changes(): void
    {
        $f = $this->salon(); $appointment = $this->appointment($f, discount: '5.00', rate: '7.50');
        $response = $this->issue($appointment)->assertCreated()->assertJsonPath('data.customer.type', 'salon_client')
            ->assertJsonPath('data.customer.id', $f['client'])->assertJsonPath('data.customer.name', 'Amina Ali')
            ->assertJsonPath('data.total', '43.00')->assertJsonPath('data.balance', '43.00');
        $invoice = $response->json('data');
        $this->assertEquals(43, array_sum(array_column($invoice['items'], 'line_total')));
        $this->assertEquals(5, array_sum(array_column($invoice['items'], 'discount_amount')));
        $this->assertEquals(3, array_sum(array_column($invoice['items'], 'tax_amount')));
        $this->assertSame('salon_appointment_service', $invoice['items'][0]['source']['type']);
        $this->assertNotNull($invoice['issued_at']);
        $this->assertDatabaseHas('billing_invoices', ['id' => $invoice['id'], 'salon_client_id' => $f['client'], 'patient_id' => null]);
        DB::table('salon_clients')->where('id', $f['client'])->update(['first_name' => 'Changed']);
        DB::table('salon_services')->whereIn('id', $f['services'])->update(['price' => 99]);
        $again = $this->issue($appointment)->assertCreated()->json('data');
        $this->assertSame($invoice, $again);
        $this->assertDatabaseCount('billing_invoices', 1);
        $this->getJson('/api/v1/billing/invoices/'.$invoice['id'])->assertOk()->assertJsonPath('data.items.0.unit_price', '15.00');
    }

    public function test_shared_balance_payments_retries_and_overpayment(): void
    {
        $f = $this->salon(); $id = $this->issue($this->appointment($f))->assertCreated()->json('data.id');
        $url = '/api/v1/billing/invoices/'.$id.'/payments';
        $payment = ['amount' => 20, 'method' => 'cash', 'idempotency_key' => 'shared-one'];
        $this->postJson($url, $payment)->assertOk()->assertJsonPath('data.status', 'partial')->assertJsonPath('data.balance', '25.00');
        $this->postJson($url, $payment)->assertOk()->assertJsonCount(1, 'data.payments')->assertJsonMissingPath('data.payments.0.idempotency_key');
        $this->postJson($url, array_replace($payment, ['amount' => 21]))->assertConflict();
        $this->postJson($url, array_replace($payment, ['amount' => 26, 'idempotency_key' => 'shared-two']))->assertUnprocessable();
        $this->postJson($url, array_replace($payment, ['amount' => 25, 'idempotency_key' => 'shared-two']))->assertOk()
            ->assertJsonPath('data.status', 'paid')->assertJsonPath('data.balance', '0.00');
        $this->postJson($url, $payment)->assertOk()->assertJsonCount(2, 'data.payments');
    }

    public function test_permission_plan_and_business_dimensions_are_independent(): void
    {
        $f = $this->salon(); $a = $this->appointment($f);
        $this->getJson('/api/v1/clinic/context')->assertJsonPath('data.business_modules.clinical', false);
        $this->permissions($f, ['appointments.view', 'appointments.view_all', 'billing.create', 'billing.payments']);
        $this->getJson('/api/v1/billing/invoices')->assertForbidden(); $this->issue($a)->assertForbidden();
        $this->permissions($f, ['appointments.view', 'appointments.view_all', 'billing.view']);
        $this->getJson('/api/v1/billing/invoices')->assertOk(); $this->issue($a)->assertForbidden();
        $this->permissions($f, ['appointments.view', 'appointments.view_all', 'billing.view', 'billing.create']);
        $id = $this->issue($a)->assertCreated()->json('data.id');
        $payload = ['amount' => 45, 'method' => 'cash', 'idempotency_key' => 'permission'];
        $this->postJson('/api/v1/billing/invoices/'.$id.'/payments', $payload)->assertForbidden();
        // A cashier can view and pay without appointment-management or clinical permissions.
        $this->permissions($f, ['billing.view', 'billing.payments']);
        $this->postJson('/api/v1/billing/invoices/'.$id.'/payments', $payload)->assertOk();
        $plan = Plan::first(); $features = $plan->features; $features['billing'] = false; $plan->update(['features' => $features]);
        $this->getJson('/api/v1/billing/invoices')->assertForbidden()->assertJsonPath('code', 'PLAN_FEATURE_UNAVAILABLE');
        $features['billing'] = true; $plan->update(['features' => $features]);
        config(['business_types.beauty-salon.modules.billing' => false]);
        $this->getJson('/api/v1/billing/invoices')->assertForbidden()->assertJsonPath('code', 'BUSINESS_MODULE_UNAVAILABLE');
    }

    public function test_clinic_shared_ledger_and_cross_tenant_ids_in_both_directions(): void
    {
        $salon = $this->salon(); $salonInvoice = $this->issue($this->appointment($salon))->assertCreated()->json('data.id');
        $clinic = $this->other('clinic'); $this->login($clinic);
        $this->getJson('/api/v1/billing/invoices')->assertOk()->assertJsonCount(0, 'data.data');
        $this->getJson('/api/v1/billing/invoices/'.$salonInvoice)->assertNotFound();
        $this->postJson('/api/v1/billing/invoices/'.$salonInvoice.'/payments', ['amount' => 1, 'method' => 'cash', 'idempotency_key' => 'foreign'])->assertNotFound();
        // Fixture only: there is deliberately no Clinic issuance endpoint in this phase.
        $clinicInvoice = DB::table('billing_invoices')->insertGetId(['tenant_id' => $clinic['tenant'], 'branch_id' => $clinic['branch'],
            'source_type' => 'test_fixture', 'source_id' => 1, 'number' => 'CLINIC-EXISTING', 'customer_name' => 'Existing patient',
            'currency' => 'USD', 'created_by' => $clinic['user'], 'created_at' => now(), 'updated_at' => now()]);
        $this->getJson('/api/v1/billing/invoices/'.$clinicInvoice)->assertOk();
        foreach ([$salon, $this->other('clinic'), $this->other('beauty-salon')] as $other) {
            $this->login($other);
            $this->getJson('/api/v1/billing/invoices/'.$clinicInvoice)->assertNotFound();
            $this->postJson('/api/v1/billing/invoices/'.$clinicInvoice.'/payments', ['amount' => 1, 'method' => 'cash', 'idempotency_key' => 'foreign'])->assertNotFound();
            if ($other['tenant'] !== $salon['tenant']) $this->getJson('/api/v1/billing/invoices/'.$salonInvoice)->assertNotFound();
        }
    }

    public function test_customer_resolution_rejects_cross_tenant_and_wrong_business_types(): void
    {
        $f = $this->salon(); $other = $this->other('beauty-salon'); $clinic = $this->other('clinic');
        $resolver = app(BillingCustomerResolver::class);
        foreach ([[$other, 'salon_client', $f['client']], [$f, 'patient', 1], [$clinic, 'salon_client', $f['client']]] as [$tenant, $type, $id]) {
            app(TenantContext::class)->set($tenant['tenant']);
            try { $resolver->resolve(Tenant::find($tenant['tenant']), $type, $id, $tenant['branch']); $this->fail('Invalid customer accepted'); }
            catch (ValidationException $e) { $this->assertArrayHasKey('customer', $e->errors()); }
        }
        app(TenantContext::class)->clear();
        $a = $this->appointment($f);
        $this->issue($a)->assertCreated();
        $this->postJson('/api/v1/salon/appointments/'.$a.'/invoice', ['salon_client_id' => $f['client'], 'total' => 1])->assertUnprocessable();
    }

    public function test_branch_restrictions_and_stale_context_headers(): void
    {
        $f = $this->salon(); $a = $this->appointment($f); $id = $this->issue($a)->assertCreated()->json('data.id');
        $branch = DB::table('branches')->insertGetId(['tenant_id' => $f['tenant'], 'name' => 'Only allowed', 'status' => 'active']);
        $member = DB::table('tenant_memberships')->where('tenant_id', $f['tenant'])->where('user_id', $f['user'])->first();
        DB::table('tenant_memberships')->where('id', $member->id)->update(['all_branches' => false]);
        DB::table('branch_memberships')->insert(['tenant_id' => $f['tenant'], 'membership_id' => $member->id, 'branch_id' => $branch]);
        $this->getJson('/api/v1/billing/invoices')->assertOk()->assertJsonCount(0, 'data.data');
        $this->getJson('/api/v1/billing/invoices/'.$id)->assertNotFound(); $this->issue($a)->assertNotFound();
        $this->postJson('/api/v1/billing/invoices/'.$id.'/payments', ['amount' => 1, 'method' => 'cash', 'idempotency_key' => 'branch'])->assertNotFound();
        $this->withHeader('X-Clinic-Context', '99999')->getJson('/api/v1/billing/invoices')->assertConflict();
    }

    public function test_monthly_limit_retries_and_payment_settings_are_preserved(): void
    {
        $f = $this->salon(); Plan::first()->update(['invoice_limit' => 1]);
        $a = $this->appointment($f); $id = $this->issue($a)->assertCreated()->json('data.id');
        $this->issue($a)->assertCreated()->assertJsonPath('data.id', $id);
        $this->issue($this->appointment($f, 'SECOND'))->assertUnprocessable()->assertJsonValidationErrors('plan');
        app(ClinicSettingsService::class)->set($f['tenant'], 'billing', ['partial_payments' => false], $f['user']);
        $url = '/api/v1/billing/invoices/'.$id.'/payments';
        $this->postJson($url, ['amount' => 1, 'method' => 'cash', 'idempotency_key' => 'partial'])->assertUnprocessable();
        $this->postJson($url, ['amount' => 45, 'method' => 'card', 'idempotency_key' => 'card'])->assertUnprocessable();
        $this->postJson($url, ['amount' => 45, 'method' => 'cash', 'idempotency_key' => 'full'])->assertOk();
    }

    public function test_additive_migration_preserves_legacy_financial_records_and_unresolved_sources(): void
    {
        $f = $this->salon(); $a = $this->appointment($f, discount: '5.00', rate: '7.50');
        $migration = require database_path('migrations/2026_09_16_100000_extend_shared_billing_foundation.php');
        $migration->down();
        $base = ['tenant_id' => $f['tenant'], 'branch_id' => $f['branch'], 'source_type' => 'salon_appointment',
            'customer_name' => 'Original name', 'currency' => 'USD', 'subtotal' => 45, 'discount' => 5, 'tax' => 3, 'total' => 43,
            'paid' => 20, 'status' => 'partial', 'created_by' => $f['user'], 'created_at' => now(), 'updated_at' => now()];
        $id = DB::table('billing_invoices')->insertGetId($base + ['source_id' => $a, 'number' => 'KEEP-0099']);
        DB::table('billing_invoices')->insert($base + ['source_id' => 999999, 'number' => 'UNRESOLVED-0100']);
        DB::table('billing_invoice_items')->insert(['tenant_id' => $f['tenant'], 'invoice_id' => $id, 'description' => 'Historical charge', 'quantity' => 1, 'unit_price' => 45, 'amount' => 45]);
        DB::table('billing_payments')->insert(['tenant_id' => $f['tenant'], 'invoice_id' => $id, 'amount' => 20, 'method' => 'cash', 'idempotency_key' => 'legacy', 'recorded_by' => $f['user'], 'paid_at' => now()]);
        DB::table('tenants')->where('id', $f['tenant'])->update(['billing_invoice_sequence' => 100]);
        $invoices = DB::table('billing_invoices')->get()->map(fn ($row) => (array) $row)->all();
        $items = DB::table('billing_invoice_items')->get()->map(fn ($row) => (array) $row)->all();
        $payments = DB::table('billing_payments')->get()->map(fn ($row) => (array) $row)->all();
        $migration->up();
        foreach ($invoices as $row) $this->assertEquals($row, array_intersect_key((array) DB::table('billing_invoices')->find($row['id']), $row));
        foreach ($items as $row) $this->assertEquals($row, array_intersect_key((array) DB::table('billing_invoice_items')->find($row['id']), $row));
        $this->assertEquals($payments, DB::table('billing_payments')->get()->map(fn ($row) => (array) $row)->all());
        $this->assertDatabaseHas('billing_invoices', ['id' => $id, 'salon_client_id' => $f['client'], 'snapshot_version' => 1]);
        $this->assertDatabaseHas('billing_invoices', ['number' => 'UNRESOLVED-0100', 'salon_client_id' => null]);
        $this->assertDatabaseHas('billing_invoice_items', ['invoice_id' => $id, 'amount' => 45, 'line_total' => null]);
        $this->assertEquals(100, DB::table('tenants')->where('id', $f['tenant'])->value('billing_invoice_sequence'));
    }

    public function test_database_customer_foreign_key_rejects_cross_tenant_assignment(): void
    {
        $f = $this->salon(); $other = $this->other('beauty-salon');
        $id = $this->issue($this->appointment($f))->assertCreated()->json('data.id');
        $client = (array) DB::table('salon_clients')->find($f['client']);
        unset($client['id']);
        $client['tenant_id'] = $other['tenant']; $client['branch_id'] = $other['branch'];
        $foreignClient = DB::table('salon_clients')->insertGetId($client);
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('billing_invoices')->where('id', $id)->update(['salon_client_id' => $foreignClient]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\{Plan, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DentalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $name = 'dental', string $business = 'dental'): array
    {
        require_once base_path('tests/Support/clinic-billing-fixture.php');
        $f = clinicBillingFixture($name, $business);
        $plan = Plan::find($f['plan']);
        $plan->update(['features' => $plan->features + ['emr' => true]]);
        $this->login($f);
        return $f;
    }

    private function login(array $f): void
    {
        $this->flushSession(); $this->app['auth']->forgetGuards();
        $this->withHeader('Origin', 'http://localhost')->actingAs(User::find($f['user']))
            ->postJson('/api/v1/session/clinic', ['clinic_id' => $f['tenant']])->assertOk();
    }

    private function root(array $f): string { return '/api/v1/dental/patients/'.$f['patient']; }

    private function procedure(array $changes = []): array
    {
        return $this->postJson('/api/v1/dental/procedures', array_replace([
            'code' => 'FILL', 'name' => 'Composite filling', 'price' => '45.50', 'active' => true,
        ], $changes))->assertCreated()->json('data');
    }

    public function test_dental_capability_plan_and_permissions_are_enforced(): void
    {
        foreach (['clinic', 'beauty-salon', 'stadium'] as $business) {
            $this->fixture($business, $business);
            $this->getJson('/api/v1/dental/options')->assertForbidden();
        }
        $f = $this->fixture();
        $this->getJson('/api/v1/dental/options')->assertOk()->assertJsonPath('data.numbering', 'Universal');
        DB::table('tenant_memberships')->where('tenant_id', $f['tenant'])->update(['permissions' => json_encode(['patients.view', 'dental.view'])]);
        $this->getJson($this->root($f).'/chart')->assertOk();
        $this->postJson('/api/v1/dental/procedures', [])->assertForbidden();
        Plan::find($f['plan'])->update(['features' => ['patient_management' => true]]);
        $this->getJson($this->root($f).'/chart')->assertForbidden()->assertJsonPath('code', 'PLAN_FEATURE_UNAVAILABLE');
    }

    public function test_catalog_validates_prices_uniqueness_and_archives_without_deletion(): void
    {
        $this->fixture();
        $p = $this->procedure();
        $this->postJson('/api/v1/dental/procedures', ['code' => 'FILL', 'name' => 'Duplicate', 'price' => 2, 'active' => true])->assertUnprocessable();
        $this->postJson('/api/v1/dental/procedures', ['code' => 'BAD', 'name' => 'Bad price', 'price' => -1, 'active' => true])->assertUnprocessable();
        $this->putJson('/api/v1/dental/procedures/'.$p['id'], ['code' => 'FILL', 'name' => 'Composite filling', 'price' => '60.00', 'active' => false])->assertOk();
        $this->getJson('/api/v1/dental/procedures')->assertOk()->assertJsonPath('data.data.0.active', false);
        $this->assertDatabaseCount('dental_procedures', 1);
    }

    public function test_universal_chart_keeps_dated_findings_and_void_reason(): void
    {
        $f = $this->fixture(); $root = $this->root($f);
        $this->getJson($root.'/chart')->assertOk()->assertJsonCount(0, 'data.findings');
        $finding = $this->postJson($root.'/findings', ['tooth' => '3', 'surfaces' => ['O'], 'condition' => 'caries', 'notes' => 'Observed cavity'])
            ->assertCreated()->assertJsonPath('data.tooth', '3')->json('data');
        $this->postJson($root.'/findings', ['tooth' => 'A', 'surfaces' => [], 'condition' => 'sound'])->assertCreated();
        foreach (['33', '0', 'U', '11x'] as $tooth) {
            $this->postJson($root.'/findings', ['tooth' => $tooth, 'condition' => 'sound'])->assertUnprocessable();
        }
        $this->postJson($root.'/findings/'.$finding['id'].'/void', [])->assertUnprocessable();
        $this->postJson($root.'/findings/'.$finding['id'].'/void', ['reason' => 'Wrong tooth selected'])->assertOk();
        $this->getJson($root.'/chart')->assertJsonCount(2, 'data.findings');
        $this->assertDatabaseHas('dental_findings', ['id' => $finding['id'], 'notes' => 'Observed cavity', 'void_reason' => 'Wrong tooth selected']);
        DB::table('patients')->where('id', $f['patient'])->update(['status' => 'archived']);
        $this->postJson($root.'/findings', ['tooth' => '3', 'condition' => 'sound'])->assertUnprocessable();
    }

    public function test_foreign_patients_and_procedures_are_hidden(): void
    {
        $a = $this->fixture('dental-a'); $p = $this->procedure();
        $b = $this->fixture('dental-b');
        $this->getJson($this->root($a).'/chart')->assertNotFound();
        $this->putJson('/api/v1/dental/procedures/'.$p['id'], ['code' => 'X', 'name' => 'Foreign', 'price' => 1, 'active' => true])->assertNotFound();
        $this->getJson($this->root($b).'/chart')->assertOk();
    }

    private function treatmentPlan(array $f, array $items = []): array
    {
        if (!$items) {
            $p = $this->procedure();
            $items = [
                ['procedure_id' => $p['id'], 'tooth' => '3', 'surfaces' => ['O'], 'visit_number' => 1, 'quantity' => 1],
                ['procedure_id' => $p['id'], 'tooth' => 'A', 'surfaces' => [], 'visit_number' => 2, 'quantity' => 1],
            ];
        }
        return $this->postJson($this->root($f).'/plans', ['title' => 'Restorative treatment', 'items' => $items])->assertCreated()->json('data');
    }

    public function test_multi_visit_plan_keeps_price_snapshots_and_completed_tooth_history(): void
    {
        $f = $this->fixture(); $plan = $this->treatmentPlan($f); $url = '/api/v1/dental/plans/'.$plan['id'];
        $this->assertSame('91.00', $plan['total']);
        $this->assertSame('draft', $plan['status']);
        $this->postJson($url.'/items/'.$plan['items'][0]['id'].'/complete', [])->assertUnprocessable();
        DB::table('dental_procedures')->update(['price' => 999, 'name' => 'New name', 'active' => false]);
        $this->postJson($url.'/status', ['status' => 'accepted', 'version' => 1])->assertOk()->assertJsonPath('data.total', '91.00');
        $this->putJson($url, ['title' => 'Changed', 'items' => [], 'version' => 2])->assertUnprocessable();
        $this->postJson($url.'/items/'.$plan['items'][0]['id'].'/complete', ['appointment_id' => $f['appointment'], 'notes' => 'Completed restoration'])
            ->assertOk()->assertJsonPath('data.status', 'accepted')->assertJsonPath('data.items.0.status', 'completed');
        $this->postJson($url.'/items/'.$plan['items'][0]['id'].'/complete', ['notes' => 'Changed by retry'])->assertOk();
        $this->assertDatabaseHas('dental_plan_items', ['id' => $plan['items'][0]['id'], 'completion_notes' => 'Completed restoration']);
        $this->postJson($url.'/items/'.$plan['items'][1]['id'].'/complete', [])->assertOk()->assertJsonPath('data.status', 'completed');
        $this->getJson($this->root($f).'/chart')->assertOk()->assertJsonCount(2, 'data.treatments');
        $this->getJson($url)->assertJsonPath('data.items.0.procedure_name', 'Composite filling')->assertJsonPath('data.items.0.unit_price', '45.50');
    }

    public function test_completed_treatment_invoices_once_using_snapshots_and_shared_payments(): void
    {
        $f = $this->fixture(); $p = $this->treatmentPlan($f); $url = '/api/v1/dental/plans/'.$p['id'];
        $item = $url.'/items/'.$p['items'][0]['id'];
        $this->postJson($item.'/invoice')->assertUnprocessable();
        $this->postJson($url.'/status', ['status' => 'accepted', 'version' => 1])->assertOk();
        $this->postJson($item.'/complete')->assertOk();
        DB::table('dental_procedures')->update(['price' => 100]);
        $invoice = $this->postJson($item.'/invoice')->assertCreated()->assertJsonPath('data.total', '45.50')
            ->assertJsonPath('data.source.type', 'dental_treatment')->assertJsonPath('data.customer.id', $f['patient'])->json('data');
        $this->postJson($item.'/invoice')->assertCreated()->assertJsonPath('data.id', $invoice['id']);
        $this->postJson($item.'/invoice', ['total' => 1])->assertUnprocessable();
        $this->postJson('/api/v1/billing/invoices/'.$invoice['id'].'/payments', ['amount' => '45.50', 'method' => 'cash', 'idempotency_key' => 'dental-payment'])
            ->assertOk()->assertJsonPath('data.status', 'paid');
        $this->assertDatabaseCount('billing_invoices', 1);
        $this->assertDatabaseCount('billing_receipts', 1);
    }

    public function test_cancel_preserves_completed_work_and_prevents_remaining_completion(): void
    {
        $f = $this->fixture(); $p = $this->treatmentPlan($f); $url = '/api/v1/dental/plans/'.$p['id'];
        $this->postJson($url.'/status', ['status' => 'accepted', 'version' => 1])->assertOk();
        $state = $this->postJson($url.'/items/'.$p['items'][0]['id'].'/complete')->assertOk()->json('data');
        $this->postJson($url.'/status', ['status' => 'cancelled', 'version' => $state['version']])->assertUnprocessable();
        $this->postJson($url.'/status', ['status' => 'cancelled', 'version' => $state['version'], 'reason' => 'Patient declined remaining visit'])
            ->assertOk()->assertJsonPath('data.items.0.status', 'completed')->assertJsonPath('data.items.1.status', 'cancelled');
        $this->postJson($url.'/items/'.$p['items'][1]['id'].'/complete')->assertUnprocessable();
        $this->postJson($url.'/items/'.$p['items'][0]['id'].'/invoice')->assertCreated();
    }

    public function test_draft_edits_require_version_and_reject_foreign_and_archived_references(): void
    {
        $f = $this->fixture(); $p = $this->treatmentPlan($f); $url = '/api/v1/dental/plans/'.$p['id'];
        $items = [['procedure_id' => $p['items'][0]['procedure_id'], 'tooth' => '32', 'visit_number' => 3, 'quantity' => 2, 'unit_price' => '12.25']];
        $this->putJson($url, ['title' => 'Revised', 'version' => 1, 'items' => $items])->assertOk()->assertJsonPath('data.total', '24.50');
        $this->putJson($url, ['title' => 'Stale', 'version' => 1, 'items' => $items])->assertStatus(409);
        $this->postJson($this->root($f).'/plans', ['title' => 'Bad tooth', 'items' => [array_replace($items[0], ['tooth' => '99'])]])->assertUnprocessable();
        $other = $this->fixture('other'); $foreignProcedure = $this->procedure(); $this->login($f);
        $this->postJson($this->root($f).'/plans', ['title' => 'Foreign procedure', 'items' => [array_replace($items[0], ['procedure_id' => $foreignProcedure['id']])]])->assertUnprocessable();
        $p = $this->getJson($url)->assertOk()->json('data');
        $this->postJson($url.'/status', ['status' => 'accepted', 'version' => 2])->assertOk();
        $this->postJson($url.'/items/'.$p['items'][0]['id'].'/complete', ['appointment_id' => $other['appointment']])->assertUnprocessable();
        DB::table('patients')->where('id', $f['patient'])->update(['status' => 'archived']);
        $this->postJson($url.'/items/'.$p['items'][0]['id'].'/complete')->assertUnprocessable();
    }

    public function test_branch_visibility_and_action_permissions_protect_plans(): void
    {
        $f = $this->fixture(); $p = $this->treatmentPlan($f); $url = '/api/v1/dental/plans/'.$p['id'];
        DB::table('tenant_memberships')->where('tenant_id', $f['tenant'])->update(['permissions' => json_encode(['dental.view', 'patients.view'])]);
        $this->getJson($url)->assertOk();
        $this->postJson($url.'/status', ['status' => 'accepted', 'version' => 1])->assertForbidden();
        $this->postJson($url.'/items/'.$p['items'][0]['id'].'/complete')->assertForbidden();
        $branch = DB::table('branches')->insertGetId(['tenant_id' => $f['tenant'], 'name' => 'Private branch', 'code' => 'PRIVATE', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('dental_plans')->where('id', $p['id'])->update(['branch_id' => $branch]);
        Plan::find($f['plan'])->update(['features' => ['patient_management' => true, 'emr' => true]]);
        $this->getJson($url)->assertNotFound();
        $this->getJson($this->root($f).'/plans')->assertJsonCount(0, 'data');
    }

    public function test_database_rejects_cross_tenant_patient_in_dental_records(): void
    {
        $a = $this->fixture('first'); $b = $this->fixture('second');
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('dental_findings')->insert(['tenant_id' => $a['tenant'], 'patient_id' => $b['patient'],
            'branch_id' => $a['branch'], 'tooth' => '1', 'surfaces' => '[]', 'condition' => 'sound', 'recorded_by' => $a['user'],
            'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_excessive_plan_amount_is_rejected_without_saving_a_partial_plan(): void
    {
        $f = $this->fixture(); $p = $this->procedure(['price' => '9999999.99']);
        $items = array_fill(0, 100, ['procedure_id' => $p['id'], 'visit_number' => 1, 'quantity' => 100]);
        $this->postJson($this->root($f).'/plans', ['title' => 'Too large', 'items' => $items])->assertUnprocessable();
        $this->assertDatabaseCount('dental_plans', 0);
    }

    public function test_accepted_plan_total_matches_sum_of_individually_invoiced_treatments(): void
    {
        $f = $this->fixture();
        app(\App\Services\ClinicSettingsService::class)->set($f['tenant'], 'billing', ['tax_rate' => 10], $f['user']);
        $p = $this->procedure(['price' => '0.05']);
        $plan = $this->treatmentPlan($f, array_fill(0, 2, ['procedure_id' => $p['id'], 'visit_number' => 1, 'quantity' => 1]));
        $url = '/api/v1/dental/plans/'.$plan['id'];
        $this->postJson($url.'/status', ['status' => 'accepted', 'version' => 1])->assertOk();
        $sum = 0;
        foreach ($plan['items'] as $item) {
            $this->postJson($url.'/items/'.$item['id'].'/complete')->assertOk();
            $sum += \App\Services\Billing\BillingMoney::cents($this->postJson($url.'/items/'.$item['id'].'/invoice')->assertCreated()->json('data.total'));
        }
        $this->assertSame(\App\Services\Billing\BillingMoney::cents($plan['total']), $sum);
    }

    public function test_linkable_appointments_are_limited_to_plan_patient_branch_and_valid_status(): void
    {
        $f = $this->fixture(); $p = $this->treatmentPlan($f);
        $url = '/api/v1/dental/plans/'.$p['id'].'/appointments';
        $this->getJson($url)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $f['appointment']);
        DB::table('appointments')->where('id', $f['appointment'])->update(['status' => 'cancelled']);
        $this->getJson($url)->assertOk()->assertJsonCount(0, 'data');
    }
}

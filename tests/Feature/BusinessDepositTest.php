<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BusinessDepositTest extends TestCase
{
    use RefreshDatabase;

    private function salon(): array
    {
        require_once base_path('tests/Support/salon-booking-fixture.php');
        $fixture = salonBookingFixture();
        DB::table('salon_services')->where('id', $fixture['services'][0])->update(['requires_deposit' => true, 'deposit_amount' => 10]);
        $this->withHeader('Origin', 'http://localhost')->actingAs(User::findOrFail($fixture['user']))
            ->postJson('/api/v1/session/clinic', ['clinic_id' => $fixture['tenant']])->assertOk();
        return $fixture;
    }

    private function appointment(array $f): int
    {
        $id = DB::table('salon_appointments')->insertGetId([
            'tenant_id' => $f['tenant'], 'branch_id' => $f['branch'], 'client_id' => $f['client'], 'stylist_id' => $f['stylist'],
            'appointment_number' => 'APT-DEPOSIT', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addMinutes(30),
            'status' => 'scheduled', 'source' => 'reception', 'currency' => 'USD', 'duration_minutes' => 30,
            'subtotal' => 15, 'discount' => 0, 'tax_rate' => 0, 'tax' => 0, 'total' => 15, 'deposit_required' => 10,
            'created_by' => $f['user'], 'updated_by' => $f['user'], 'created_at' => now(), 'updated_at' => now(),
        ]);
        $service = DB::table('salon_services')->find($f['services'][0]);
        DB::table('salon_appointment_services')->insert([
            'tenant_id' => $f['tenant'], 'appointment_id' => $id, 'service_id' => $service->id,
            'name' => $service->name, 'duration_minutes' => 30, 'unit_price' => 15, 'amount' => 15,
            'deposit_amount' => 10, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return $id;
    }

    public function test_salon_collects_deposit_once_and_final_invoice_reconciles(): void
    {
        $f = $this->salon();
        $appointment = $this->appointment($f);
        $payload = ['amount' => '10.00', 'method' => 'cash', 'idempotency_key' => 'deposit-one'];

        $deposit = $this->postJson("/api/v1/salon/appointments/{$appointment}/deposit/payments", $payload)
            ->assertOk()->assertJsonPath('data.source.type', 'salon_appointment_deposit')
            ->assertJsonPath('data.total', '10.00')->assertJsonPath('data.paid', '10.00')->json('data');
        $this->postJson("/api/v1/salon/appointments/{$appointment}/deposit/payments", $payload)
            ->assertOk()->assertJsonPath('data.id', $deposit['id']);
        $this->assertDatabaseCount('billing_invoices', 1);
        $this->assertDatabaseCount('billing_payments', 1);
        $this->assertDatabaseCount('billing_receipts', 1);

        DB::table('salon_appointments')->where('id', $appointment)->update(['status' => 'completed', 'completed_at' => now()]);
        $final = $this->postJson("/api/v1/salon/appointments/{$appointment}/invoice")
            ->assertCreated()->assertJsonPath('data.credit', '10.00')->assertJsonPath('data.total', '5.00')->json('data');
        $this->assertDatabaseHas('billing_deposit_allocations', [
            'deposit_invoice_id' => $deposit['id'], 'final_invoice_id' => $final['id'], 'amount' => 10,
        ]);
    }

    public function test_salon_deposit_rejects_overpayment_and_cross_tenant_source(): void
    {
        $f = $this->salon();
        $appointment = $this->appointment($f);
        $this->postJson("/api/v1/salon/appointments/{$appointment}/deposit/payments", [
            'amount' => '10.01', 'method' => 'cash', 'idempotency_key' => 'too-much',
        ])->assertUnprocessable();

        DB::table('users')->where('id', $f['user'])->update(['email' => 'first-booking-owner@example.test']);
        $other = salonBookingFixture();
        $foreign = $this->appointment($other);
        $this->flushSession(); $this->app['auth']->forgetGuards();
        $this->withHeader('Origin', 'http://localhost')->actingAs(User::findOrFail($f['user']))
            ->postJson('/api/v1/session/clinic', ['clinic_id' => $f['tenant']])->assertOk();
        $this->postJson("/api/v1/salon/appointments/{$foreign}/deposit/payments", [
            'amount' => '1.00', 'method' => 'cash', 'idempotency_key' => 'foreign',
        ])->assertNotFound();
    }

    public function test_paid_salon_deposit_requires_a_disposition_and_can_be_refunded_once(): void
    {
        $f = $this->salon();
        DB::table('tenant_settings')->updateOrInsert(['tenant_id' => $f['tenant'], 'section' => 'billing'], [
            'values' => json_encode(['refunds' => true]), 'created_at' => now(), 'updated_at' => now(),
        ]);
        app(\App\Services\ClinicSettingsService::class)->all($f['tenant']);
        \Illuminate\Support\Facades\Cache::forget("clinic-settings.{$f['tenant']}");
        $appointment = $this->appointment($f);
        $this->postJson("/api/v1/salon/appointments/{$appointment}/deposit/payments", [
            'amount' => '10.00', 'method' => 'cash', 'idempotency_key' => 'to-refund',
        ])->assertOk();

        $this->postJson("/api/v1/salon/appointments/{$appointment}/cancel", ['reason' => 'Client cancelled'])
            ->assertUnprocessable()->assertJsonValidationErrors('deposit_disposition');
        $payload = ['reason' => 'Client cancelled', 'deposit_disposition' => 'refund', 'deposit_idempotency_key' => 'refund-once'];
        $this->postJson("/api/v1/salon/appointments/{$appointment}/cancel", $payload)->assertOk()
            ->assertJsonPath('data.deposit.refunded', '10.00');
        $this->assertDatabaseHas('billing_payments', ['type' => 'refund', 'amount' => -10]);
        $this->assertDatabaseCount('billing_receipts', 2);
    }

    public function test_clinic_collects_appointment_deposit_and_credits_final_invoice(): void
    {
        require_once base_path('tests/Support/clinic-billing-fixture.php');
        $f = clinicBillingFixture('deposit-clinic');
        DB::table('appointments')->where('id', $f['appointment'])->update([
            'status' => 'scheduled', 'completed_at' => null, 'deposit_basis' => 20, 'deposit_required' => 5,
        ]);
        $this->withHeader('Origin', 'http://localhost')->actingAs(User::findOrFail($f['user']))
            ->postJson('/api/v1/session/clinic', ['clinic_id' => $f['tenant']])->assertOk();

        $deposit = $this->postJson("/api/v1/clinic/appointments/{$f['appointment']}/deposit/payments", [
            'amount' => '5.00', 'method' => 'cash', 'idempotency_key' => 'clinic-deposit',
        ])->assertOk()->assertJsonPath('data.total', '5.00')->json('data');
        DB::table('appointments')->where('id', $f['appointment'])->update(['status' => 'completed', 'completed_at' => now()]);
        $this->postJson("/api/v1/clinic/appointments/{$f['appointment']}/invoice")
            ->assertCreated()->assertJsonPath('data.credit', '5.00')->assertJsonPath('data.total', '15.00');
        $this->assertDatabaseHas('billing_deposit_allocations', ['deposit_invoice_id' => $deposit['id'], 'amount' => 5]);
    }

    public function test_dental_plan_deposit_is_allocated_across_treatment_invoices(): void
    {
        require_once base_path('tests/Support/clinic-billing-fixture.php');
        $f = clinicBillingFixture('deposit-dental', 'dental');
        $base = ['tenant_id' => $f['tenant'], 'created_at' => now(), 'updated_at' => now()];
        $plan = DB::table('dental_plans')->insertGetId($base + [
            'patient_id' => $f['patient'], 'branch_id' => $f['branch'], 'title' => 'Deposit plan', 'status' => 'accepted',
            'currency' => 'USD', 'tax_rate' => 0, 'version' => 2, 'created_by' => $f['user'], 'accepted_at' => now(),
            'deposit_basis' => 40, 'deposit_required' => 15,
        ]);
        $procedure = DB::table('dental_procedures')->insertGetId($base + ['code' => 'DEP', 'name' => 'Deposit treatment', 'price' => 20, 'active' => true]);
        $items = [];
        foreach ([1, 2] as $visit) $items[] = DB::table('dental_plan_items')->insertGetId($base + [
            'surfaces' => '[]',
            'plan_id' => $plan, 'procedure_id' => $procedure, 'procedure_code' => 'DEP', 'procedure_name' => 'Deposit treatment',
            'visit_number' => $visit, 'quantity' => 1, 'unit_price' => 20, 'status' => 'completed', 'completed_at' => now(),
            'completed_by' => $f['user'],
        ]);
        $this->withHeader('Origin', 'http://localhost')->actingAs(User::findOrFail($f['user']))
            ->postJson('/api/v1/session/clinic', ['clinic_id' => $f['tenant']])->assertOk();

        $this->postJson("/api/v1/dental/plans/{$plan}/deposit/payments", [
            'amount' => '15.00', 'method' => 'cash', 'idempotency_key' => 'dental-deposit',
        ])->assertOk()->assertJsonPath('data.total', '15.00');
        $this->postJson("/api/v1/dental/plans/{$plan}/items/{$items[0]}/invoice")
            ->assertCreated()->assertJsonPath('data.credit', '15.00')->assertJsonPath('data.total', '5.00');
        $this->postJson("/api/v1/dental/plans/{$plan}/items/{$items[1]}/invoice")
            ->assertCreated()->assertJsonPath('data.credit', '0.00')->assertJsonPath('data.total', '20.00');
        $this->assertDatabaseCount('billing_deposit_allocations', 1);
    }
}

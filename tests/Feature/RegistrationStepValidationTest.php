<?php

namespace Tests\Feature;

use App\Models\{BusinessType, Plan, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Notification};
use Tests\TestCase;

class RegistrationStepValidationTest extends TestCase
{
    use RefreshDatabase;

    private function account(): array
    {
        return ['owner_name' => 'New Owner', 'owner_email' => 'new@example.test',
            'owner_password' => 'StrongPassword123', 'owner_password_confirmation' => 'StrongPassword123'];
    }

    public function test_account_step_checks_required_fields_email_uniqueness_and_password_rules_without_writes(): void
    {
        Notification::fake();
        User::factory()->create(['email' => 'taken@example.test']);
        $this->postJson('/register/validate', ['step' => 1])->assertUnprocessable()
            ->assertJsonValidationErrors(['owner_name', 'owner_email', 'owner_password', 'owner_password_confirmation']);
        foreach ([
            ['owner_email', 'not-an-email', 'owner_email'],
            ['owner_email', 'taken@example.test', 'owner_email'],
            ['owner_password_confirmation', 'DifferentPassword123', 'owner_password_confirmation'],
            ['owner_password', 'weakpassword', 'owner_password'],
            ['owner_password', 'alllowercase123', 'owner_password'],
            ['owner_password', 'MissingNumbers', 'owner_password'],
        ] as [$field, $value, $error]) {
            $this->postJson('/register/validate', array_replace($this->account(), ['step' => 1, $field => $value]))
                ->assertUnprocessable()->assertJsonValidationErrors($error);
        }
        $this->postJson('/register/validate', $this->account() + ['step' => 1])->assertOk()->assertExactJson(['valid' => true]);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('tenants', 0);
        $this->assertDatabaseCount('subscriptions', 0);
        Notification::assertNothingSent();
    }

    public function test_selection_and_details_steps_reject_missing_inactive_or_invalid_values(): void
    {
        $this->seed(\Database\Seeders\BusinessTypeSeeder::class);
        $type = BusinessType::where('status', 'active')->firstOrFail();
        $plan = Plan::create(['name' => 'Trial', 'status' => 'active', 'trial_days' => 14, 'branch_limit' => 1, 'member_limit' => 2]);
        foreach ([2 => 'business_type_id', 3 => 'plan_id'] as $step => $field) {
            $this->postJson('/register/validate', ['step' => $step])->assertUnprocessable()->assertJsonValidationErrors($field);
            $this->postJson('/register/validate', ['step' => $step, $field => 999999])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->postJson('/register/validate', ['step' => 2, 'business_type_id' => $type->id])->assertOk();
        $this->postJson('/register/validate', ['step' => 3, 'plan_id' => $plan->id])->assertOk();
        $type->update(['status' => 'inactive']);
        $plan->update(['status' => 'inactive']);
        $this->postJson('/register/validate', ['step' => 2, 'business_type_id' => $type->id])->assertUnprocessable()->assertJsonValidationErrors('business_type_id');
        $this->postJson('/register/validate', ['step' => 3, 'plan_id' => $plan->id])->assertUnprocessable()->assertJsonValidationErrors('plan_id');
        $plan->update(['status' => 'active', 'trial_days' => 0]);
        $this->postJson('/register/validate', ['step' => 3, 'plan_id' => $plan->id])->assertUnprocessable()->assertJsonValidationErrors('plan_id');
        $this->postJson('/register/validate', ['step' => 4, 'timezone' => 'Invalid/Zone'])->assertUnprocessable()->assertJsonValidationErrors(['name', 'timezone']);
        $this->postJson('/register/validate', ['step' => 4, 'name' => 'My Business', 'timezone' => 'Africa/Nairobi'])->assertOk();
        $this->postJson('/register/validate', ['step' => 5])->assertUnprocessable()->assertJsonValidationErrors('step');
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('tenants', 0);
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_final_validation_rechecks_data_and_only_final_submission_creates_one_trial(): void
    {
        Notification::fake();
        $this->seed(\Database\Seeders\BusinessTypeSeeder::class);
        $type = BusinessType::where('status', 'active')->firstOrFail();
        $plan = Plan::create(['name' => 'Trial', 'status' => 'active', 'trial_days' => 14, 'branch_limit' => 1, 'member_limit' => 2]);
        $data = $this->account() + ['business_type_id' => $type->id, 'plan_id' => $plan->id, 'name' => 'My Business', 'timezone' => 'Africa/Nairobi'];
        foreach (range(1, 4) as $step) $this->postJson('/register/validate', $data + ['step' => $step])->assertOk();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('tenants', 0);
        $this->assertDatabaseCount('subscriptions', 0);
        $plan->update(['status' => 'inactive']);
        $this->postJson('/register', $data)->assertUnprocessable()->assertJsonValidationErrors('plan_id');
        $plan->update(['status' => 'active']);
        $this->postJson('/register', array_replace($data, ['owner_password_confirmation' => 'Mismatch']))->assertUnprocessable()->assertJsonValidationErrors('owner_password_confirmation');
        $this->postJson('/register', $data)->assertCreated();
        $this->postJson('/register', $data)->assertUnprocessable()->assertJsonValidationErrors('owner_email');
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('tenants', 1);
        $this->assertDatabaseCount('subscriptions', 1);
        $this->assertSame(1, DB::table('subscriptions')->where('status', 'trial')->whereNotNull('trial_ends_at')->count());
    }
}

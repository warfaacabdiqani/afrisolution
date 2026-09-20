<?php

namespace Tests\Feature;

use App\Models\BusinessType;
use App\Models\Plan;
use App\Models\PlatformRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use App\Notifications\VerifyAfrisoEmail;
use Tests\TestCase;

class SelfRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\BusinessTypeSeeder::class);
        $this->withHeader('Origin', 'http://localhost');
        Notification::fake();
    }

    private function plan(string $name, int $days): Plan
    {
        return Plan::create(['name' => $name, 'status' => 'active', 'branch_limit' => 2, 'member_limit' => 5,
            'trial_days' => $days, 'features' => ['patient_management' => true, 'appointments' => true, 'clinicians' => true,
                'emr' => true, 'prescriptions' => true, 'pharmacy' => true, 'billing' => true, 'basic_reports' => true]]);
    }

    private function payload(BusinessType $type, Plan $plan, string $email): array
    {
        return ['owner_name' => 'New Owner', 'owner_email' => $email, 'owner_password' => 'SecurePass12345',
            'owner_password_confirmation' => 'SecurePass12345', 'business_type_id' => $type->id,
            'plan_id' => $plan->id, 'name' => 'New Business', 'timezone' => 'Africa/Nairobi'];
    }

    public function test_clinic_and_salon_self_registration_use_existing_trial_and_platform_businesses(): void
    {
        $plan = $this->plan('Pro', 21);
        foreach (['clinic' => 'Main Branch', 'beauty-salon' => 'Main Location'] as $slug => $location) {
            $this->flushSession();
            $this->app['auth']->forgetGuards();
            $type = BusinessType::where('slug', $slug)->firstOrFail();
            $email = $slug.'-owner@example.test';
            $options = $this->getJson('/api/v1/public/registration')->assertOk()->json('data');
            $this->assertContains($type->id, collect($options['business_types'])->pluck('id')->all());
            $this->assertContains($plan->id, collect($options['plans'])->pluck('id')->all());

            $id = $this->postJson('/register', $this->payload($type, $plan, $email))->assertCreated()
                ->assertJsonPath('data.business_name', 'New Business')->assertJsonPath('data.plan', 'Pro')->json('data.tenant_id');
            $this->assertDatabaseHas('tenants', ['id' => $id, 'business_type_id' => $type->id]);
            $this->assertDatabaseHas('branches', ['tenant_id' => $id, 'name' => $location]);
            $this->assertDatabaseHas('tenant_memberships', ['tenant_id' => $id, 'user_id' => User::where('email', $email)->value('id'), 'role' => 'owner']);
            $subscription = DB::table('subscriptions')->where('tenant_id', $id)->first();
            $this->assertSame($plan->id, $subscription->plan_id);
            $this->assertSame('trial', $subscription->status);
            $this->assertTrue(now()->addDays(20)->lt($subscription->trial_ends_at));
            $this->getJson('/api/v1/session')->assertJsonPath('data.active_tenant_id', $id);
            $user = User::where('email', $email)->firstOrFail();
            $this->assertFalse($user->hasVerifiedEmail());
            Notification::assertSentTo($user, VerifyAfrisoEmail::class);
            $this->getJson('/api/v1/clinic/context')->assertForbidden()->assertJsonPath('code', 'EMAIL_VERIFICATION_REQUIRED');
            $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1($email)], absolute: false);
            $this->get($url)->assertRedirect('/app/verify-email?verified=1');
            $this->assertTrue($user->fresh()->hasVerifiedEmail());
            $this->getJson('/api/v1/clinic/context')->assertOk()->assertJsonPath('data.business_type.slug', $slug)->assertJsonPath('data.role', 'owner');
            $this->getJson('/api/v1/clinic/dashboard')->assertOk();
            $this->postJson('/register', $this->payload($type, $plan, $email))->assertUnprocessable();
            $this->assertSame(1, DB::table('subscriptions')->where('tenant_id', $id)->count());
            $this->postJson('/logout')->assertNoContent();
            $this->postJson('/login', ['email' => $email, 'password' => 'SecurePass12345'])->assertSuccessful();
            $this->postJson('/logout')->assertNoContent();
        }

        $admin = User::factory()->create(['is_platform_admin' => true]);
        $admin->platformRoles()->sync([PlatformRole::where('slug', 'super-administrator')->value('id')]);
        $this->actingAs($admin);
        $list = $this->getJson('/api/v1/platform/tenants')->assertOk()->json('data');
        $this->assertCount(2, $list['data'] ?? $list);
    }

    public function test_registration_rejects_inactive_type_and_plan_without_creating_account(): void
    {
        $type = BusinessType::where('slug', 'clinic')->firstOrFail();
        $plan = $this->plan('Starter', 14);
        $type->update(['status' => 'inactive']);
        $this->postJson('/register', $this->payload($type, $plan, 'invalid@example.test'))->assertUnprocessable();
        $type->update(['status' => 'active']);
        $plan->update(['status' => 'inactive']);
        $this->postJson('/register', $this->payload($type, $plan, 'invalid@example.test'))->assertUnprocessable();
        $plan->update(['status' => 'active', 'trial_days' => 0]);
        $this->postJson('/register', $this->payload($type, $plan, 'invalid@example.test'))->assertUnprocessable();
        $this->assertDatabaseMissing('users', ['email' => 'invalid@example.test']);
        $this->assertDatabaseCount('tenants', 0);
    }
}

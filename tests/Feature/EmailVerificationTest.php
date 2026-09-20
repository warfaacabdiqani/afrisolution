<?php

namespace Tests\Feature;

use App\Models\{BusinessType, Plan, User};
use App\Notifications\VerifyAfrisoEmail;
use App\Services\SystemSettingsService;
use Database\Seeders\BusinessTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Notification, URL};
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BusinessTypeSeeder::class);
        $this->withHeader('Origin', 'http://localhost');
        Notification::fake();
    }

    private function register(string $email = 'new-owner@example.test'): User
    {
        $plan = Plan::create(['name' => 'Verification Pro', 'status' => 'active', 'branch_limit' => 2, 'member_limit' => 5, 'trial_days' => 14, 'features' => ['patient_management' => true]]);
        $type = BusinessType::where('slug', 'clinic')->firstOrFail();
        $this->postJson('/register', [
            'owner_name' => 'New Owner', 'owner_email' => $email, 'owner_password' => 'SecurePass12345',
            'owner_password_confirmation' => 'SecurePass12345', 'business_type_id' => $type->id,
            'plan_id' => $plan->id, 'name' => 'Verification Clinic', 'timezone' => 'Africa/Nairobi',
        ])->assertCreated()->assertJsonPath('data.email_verification_required', true)->assertJsonPath('data.verification_email_sent', true);
        return User::where('email', $email)->firstOrFail();
    }

    private function link(User $user, int $minutes = 60, ?string $hash = null): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes($minutes), [
            'id' => $user->id, 'hash' => $hash ?? sha1($user->email),
        ], absolute: false);
    }

    public function test_new_owner_gets_professional_signed_email_and_only_verification_access(): void
    {
        $user = $this->register();
        $this->assertNull($user->email_verified_at);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('tenants', 1);
        $this->assertDatabaseCount('subscriptions', 1);
        Notification::assertSentTo($user, VerifyAfrisoEmail::class, function ($notification) use ($user) {
            $mail = $notification->toMail($user);
            $this->assertSame('Verify your Afriso email address', $mail->subject);
            $this->assertStringContainsString('/email/verify/'.$user->id.'/', $mail->actionUrl);
            $this->assertStringNotContainsString('SecurePass12345', $mail->actionUrl);
            $this->assertStringNotContainsString('smtp', $mail->actionUrl);
            return true;
        });
        $this->getJson('/api/v1/session')->assertOk()->assertJsonPath('data.email_verified', false);
        $this->getJson('/api/v1/clinic/context')->assertForbidden()->assertJsonPath('code', 'EMAIL_VERIFICATION_REQUIRED');
        $this->getJson('/api/v1/clinic/dashboard')->assertForbidden()->assertJsonPath('code', 'EMAIL_VERIFICATION_REQUIRED');
        $this->get('/app/verify-email')->assertOk();
    }

    public function test_signed_link_verifies_once_and_rejects_tampering_expiry_wrong_hash_and_other_account(): void
    {
        $user = $this->register();
        $other = User::factory()->unverified()->create();
        $this->get($this->link($user, -1))->assertForbidden();
        $this->get($this->link($user, 60, sha1('wrong@example.test')))->assertForbidden();
        $valid = $this->link($user);
        $this->get(str_replace((string) $user->id, (string) $other->id, $valid))->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
        $this->get($valid)->assertRedirect('/app/verify-email?verified=1');
        $this->get($valid)->assertRedirect('/app/verify-email?verified=1');
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertFalse($other->fresh()->hasVerifiedEmail());
        $this->assertDatabaseCount('tenants', 1);
        $this->assertDatabaseCount('subscriptions', 1);
        $this->getJson('/api/v1/clinic/context')->assertOk();
        $this->getJson('/api/v1/session')->assertJsonPath('data.email_verified', true);
    }

    public function test_resend_is_authenticated_idempotent_and_rate_limited(): void
    {
        $user = $this->register();
        Notification::fake();
        for ($i = 0; $i < 6; $i++) $this->postJson('/api/v1/email/verification/resend')->assertOk()->assertJsonPath('message', 'Verification email sent.');
        Notification::assertSentToTimes($user, VerifyAfrisoEmail::class, 6);
        $this->postJson('/api/v1/email/verification/resend')->assertStatus(429);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('tenants', 1);
        $this->assertDatabaseCount('subscriptions', 1);
    }

    public function test_existing_and_admin_created_users_remain_accessible_and_email_changes_require_verification(): void
    {
        $legacy = User::factory()->unverified()->create();
        $migration = require database_path('migrations/2026_09_20_000004_backfill_existing_email_verification.php');
        $migration->up();
        $this->assertTrue($legacy->fresh()->hasVerifiedEmail());
        $this->actingAs($legacy->fresh(), 'web')->getJson('/api/v1/session')->assertOk()->assertJsonPath('data.email_verified', true);
        $legacy = $legacy->fresh();
        $legacy->email = 'changed-address@example.test';
        $legacy->save();
        $this->assertFalse($legacy->fresh()->hasVerifiedEmail());
        Notification::assertSentTo($legacy, VerifyAfrisoEmail::class);
        $this->app['auth']->forgetGuards();
        $this->actingAs($legacy->fresh(), 'web');
        $this->getJson('/api/v1/clinic/context')->assertForbidden()->assertJsonPath('code', 'EMAIL_VERIFICATION_REQUIRED');
    }
}

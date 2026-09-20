<?php

namespace Tests\Feature;

use App\Models\{BusinessType, Plan, PlatformRole, User};
use App\Services\{PlatformService, SystemSettingsService};
use Database\Seeders\BusinessTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WhatsAppFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BusinessTypeSeeder::class);
    }

    private function business(string $slug, bool $feature = true): array
    {
        $actor = User::factory()->create();
        $plan = Plan::create(['name' => 'WhatsApp '.$slug, 'branch_limit' => 2, 'member_limit' => 10, 'trial_days' => 14, 'features' => ['whatsapp_notifications' => $feature]]);
        $tenant = app(PlatformService::class)->createTenant(['name' => $slug, 'slug' => $slug, 'business_type_id' => BusinessType::where('slug', $slug)->value('id'), 'timezone' => 'Africa/Nairobi', 'plan_id' => $plan->id, 'owner_name' => 'Owner', 'owner_email' => $slug.'@wa.test', 'owner_password' => 'SecurePass12345'], $actor->id);
        $owner = User::where('email', $slug.'@wa.test')->firstOrFail();
        return [$tenant, $owner, $plan];
    }

    private function select(User $user, int $tenant): void
    {
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->withHeader('Origin', 'http://localhost')->actingAs($user, 'web')->postJson('/api/v1/session/clinic', ['clinic_id' => $tenant])->assertOk();
    }

    private function connection(string $number = '123456789'): array
    {
        return ['business_account_id' => '998877', 'phone_number_id' => $number, 'display_phone_number' => '+252 61 123 4567', 'display_name' => 'Business', 'access_token' => 'super-secret-access-token'];
    }

    public function test_tenant_connection_is_isolated_encrypted_and_audited_without_secret(): void
    {
        [$clinic, $owner] = $this->business('clinic');
        $this->select($owner, $clinic->id);
        $this->getJson('/api/v1/whatsapp/connection')->assertOk()->assertJsonPath('data.status', 'not_configured');
        $this->putJson('/api/v1/whatsapp/connection', $this->connection())->assertOk()->assertJsonPath('data.phone_number_id', '123456789')->assertDontSee('super-secret-access-token');
        $row = DB::table('whatsapp_connections')->where('tenant_id', $clinic->id)->first();
        $this->assertNotEquals('super-secret-access-token', $row->access_token);
        $this->assertStringNotContainsString('super-secret-access-token', DB::table('platform_audit_logs')->latest('id')->value('metadata') ?? '');
        [$salon, $salonOwner] = $this->business('beauty-salon');
        $this->select($salonOwner, $salon->id);
        $this->getJson('/api/v1/whatsapp/connection?tenant_id='.$clinic->id)->assertOk()->assertJsonPath('data.status', 'not_configured');
        $this->putJson('/api/v1/whatsapp/connection', $this->connection())->assertUnprocessable();
        $this->putJson('/api/v1/whatsapp/connection', $this->connection('567890123'))->assertOk();
        $this->assertDatabaseCount('whatsapp_connections', 2);
    }

    public function test_permissions_and_plan_feature_are_enforced(): void
    {
        [$clinic, $owner, $plan] = $this->business('clinic');
        $this->select($owner, $clinic->id);
        DB::table('tenant_memberships')->where('tenant_id', $clinic->id)->update(['permissions' => json_encode(['whatsapp.view'])]);
        $this->getJson('/api/v1/whatsapp/connection')->assertOk();
        $this->putJson('/api/v1/whatsapp/connection', $this->connection())->assertForbidden();
        $plan->update(['features' => ['whatsapp_notifications' => false]]);
        $this->getJson('/api/v1/whatsapp/connection')->assertForbidden();
    }

    public function test_platform_overview_never_returns_tenant_token(): void
    {
        [$clinic, $owner] = $this->business('clinic');
        $this->select($owner, $clinic->id);
        $this->putJson('/api/v1/whatsapp/connection', $this->connection())->assertOk();
        [$salon] = $this->business('beauty-salon');
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $admin->platformRoles()->sync([PlatformRole::where('slug', 'super-administrator')->value('id')]);
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->actingAs($admin, 'web')->getJson('/api/v1/platform/whatsapp/connections')->assertOk()->assertSee('123456789')->assertSee($salon->name)->assertSee('not_configured')->assertDontSee('super-secret-access-token');
    }

    public function test_platform_webhook_secrets_are_encrypted_masked_and_absent_from_audit(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $admin->platformRoles()->sync([PlatformRole::where('slug', 'super-administrator')->value('id')]);
        $this->withHeader('Origin', 'http://localhost')->actingAs($admin, 'web');
        $values = $this->getJson('/api/v1/platform/settings')->assertOk()->json('data.notifications');
        $values['whatsapp_enabled'] = true;
        $values['whatsapp_app_id'] = '12345';
        $values['whatsapp_secret'] = 'private-app-secret';
        $values['whatsapp_verify_token'] = 'private-verify-token';
        $this->putJson('/api/v1/platform/settings/notifications', $values)->assertOk()->assertDontSee('private-app-secret')->assertDontSee('private-verify-token');
        foreach (['whatsapp_secret' => 'private-app-secret', 'whatsapp_verify_token' => 'private-verify-token'] as $key => $secret) {
            $stored = DB::table('system_settings')->where('key', 'notifications.'.$key)->first();
            $this->assertTrue((bool) $stored->is_encrypted);
            $this->assertNotEquals($secret, $stored->value);
            $this->assertEquals($secret, app(SystemSettingsService::class)->get('notifications.'.$key));
        }
        $this->getJson('/api/v1/platform/settings')->assertOk()->assertDontSee('private-app-secret')->assertDontSee('private-verify-token');
        $audit = DB::table('platform_audit_logs')->latest('id')->first();
        $this->assertStringNotContainsString('private-app-secret', json_encode($audit));
        $this->assertStringNotContainsString('private-verify-token', json_encode($audit));
    }

    public function test_webhook_challenge_signature_tenant_resolution_and_duplicate_delivery(): void
    {
        [$clinic, $owner] = $this->business('clinic');
        $this->select($owner, $clinic->id);
        $this->putJson('/api/v1/whatsapp/connection', $this->connection())->assertOk();
        app(SystemSettingsService::class)->setSection('notifications', ['whatsapp_enabled' => true, 'whatsapp_verify_token' => 'verify-me', 'whatsapp_secret' => 'app-secret']);
        $this->get('/api/v1/public/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=abc123')->assertOk()->assertSeeText('abc123');
        $this->get('/api/v1/public/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=abc123')->assertForbidden();
        $payload = json_encode(['entry' => [['changes' => [['value' => ['metadata' => ['phone_number_id' => '123456789'], 'messages' => [['id' => 'wamid.1']]]]]]]]);
        $this->call('POST', '/api/v1/public/whatsapp/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json'], $payload)->assertForbidden();
        $signature = 'sha256='.hash_hmac('sha256', $payload, 'app-secret');
        foreach (range(1, 2) as $_) $this->call('POST', '/api/v1/public/whatsapp/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => $signature], $payload)->assertOk();
        $this->assertDatabaseHas('whatsapp_webhook_events', ['tenant_id' => $clinic->id, 'provider_event_id' => 'wamid.1']);
        $this->assertDatabaseCount('whatsapp_webhook_events', 1);
        [$salon, $salonOwner] = $this->business('beauty-salon');
        $this->select($salonOwner, $salon->id);
        $this->putJson('/api/v1/whatsapp/connection', $this->connection('222222222'))->assertOk();
        $salonPayload = str_replace(['123456789', 'wamid.1'], ['222222222', 'wamid.2'], $payload);
        $this->call('POST', '/api/v1/public/whatsapp/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $salonPayload, 'app-secret')], $salonPayload)->assertOk();
        $this->assertDatabaseHas('whatsapp_webhook_events', ['tenant_id' => $salon->id, 'provider_event_id' => 'wamid.2']);
        $unknown = str_replace('123456789', '000000000', $payload);
        $this->call('POST', '/api/v1/public/whatsapp/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $unknown, 'app-secret')], $unknown)->assertOk();
        $this->assertDatabaseCount('whatsapp_webhook_events', 2);
        $malformed = json_encode(['entry' => 'unexpected']);
        $this->call('POST', '/api/v1/public/whatsapp/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $malformed, 'app-secret')], $malformed)->assertOk();
        $this->assertDatabaseCount('whatsapp_webhook_events', 2);
    }
}

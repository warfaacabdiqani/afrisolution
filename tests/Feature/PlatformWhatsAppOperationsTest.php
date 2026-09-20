<?php

namespace Tests\Feature;

use App\Jobs\{ProcessPlatformWhatsAppWebhookEvent, SendPlatformWhatsAppTemplateMessage};
use App\Models\{BusinessType, Plan, PlatformRole, User};
use App\Services\{MetaWhatsAppClient, PlatformService, SystemSettingsService, WhatsAppStatusService};
use Database\Seeders\BusinessTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Http, Queue};
use Tests\TestCase;

class PlatformWhatsAppOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BusinessTypeSeeder::class);
        app(SystemSettingsService::class)->setSection('notifications', ['whatsapp_enabled' => true, 'whatsapp_secret' => 'app-secret']);
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/message_templates')) return Http::response(['data' => [[
                'id' => 'meta-platform', 'name' => 'afriso_welcome', 'language' => 'en_US', 'category' => 'UTILITY',
                'status' => 'APPROVED', 'components' => [['type' => 'BODY', 'text' => 'Hello {{1}}']],
            ]]], 200);
            if (str_contains($request->url(), '/messages')) return Http::response(['messages' => [['id' => 'wamid.platform']]], 200);
            return Http::response([], 404);
        });
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $admin->platformRoles()->sync([PlatformRole::where('slug', 'super-administrator')->value('id')]);
        $this->withHeader('Origin', 'http://localhost')->actingAs($admin, 'web');
        return $admin;
    }

    private function setupConnection(): int
    {
        $this->putJson('/api/v1/platform/whatsapp/connection', [
            'business_account_id' => 'platform-waba', 'phone_number_id' => 'platform-phone',
            'display_phone_number' => '+252611234567', 'access_token' => 'platform-private-token',
        ])->assertOk()->assertDontSee('platform-private-token');
        $row = DB::table('platform_whatsapp_connections')->first();
        $this->assertNotEquals('platform-private-token', $row->access_token);
        return $row->id;
    }

    public function test_platform_sync_purpose_manual_send_and_status_use_only_platform_records(): void
    {
        $this->admin();
        $connectionId = $this->setupConnection();
        $this->postJson('/api/v1/platform/whatsapp/templates/sync')->assertOk()->assertJsonPath('count', 1);
        $id = DB::table('platform_whatsapp_templates')->value('id');
        $this->patchJson("/api/v1/platform/whatsapp/templates/$id/purpose", ['purpose' => 'welcome'])->assertOk()->assertJsonPath('data.purpose', 'welcome');
        $this->patchJson("/api/v1/platform/whatsapp/templates/$id/purpose", ['purpose' => 'Meta utility'])->assertUnprocessable();
        $this->postJson('/api/v1/platform/whatsapp/templates/sync')->assertOk();
        $this->assertDatabaseHas('platform_whatsapp_templates', ['id' => $id, 'purpose' => 'welcome']);
        Queue::fake();
        $this->postJson('/api/v1/platform/whatsapp/messages', ['recipient' => '611234567', 'template_id' => $id, 'parameters' => ['body' => ['Owner']]])->assertUnprocessable()->assertJsonValidationErrors('recipient');
        $messageId = $this->postJson('/api/v1/platform/whatsapp/messages', ['recipient' => '+252 61 123 4567', 'template_id' => $id, 'parameters' => ['body' => ['Owner']]])->assertAccepted()->assertJsonPath('data.status', 'queued')->assertDontSee('Owner')->json('data.id');
        Queue::assertPushed(SendPlatformWhatsAppTemplateMessage::class, function ($job) use ($messageId) {
            $this->assertSame($messageId, $job->messageId);
            $this->assertStringNotContainsString('platform-private-token', serialize($job));
            return true;
        });
        (new SendPlatformWhatsAppTemplateMessage($messageId))->handle(app(MetaWhatsAppClient::class));
        Http::assertSent(fn ($request) => $request->method() === 'POST' && $request->hasHeader('Authorization', 'Bearer platform-private-token') && str_contains($request->url(), '/platform-phone/messages'));
        $this->assertDatabaseHas('platform_whatsapp_messages', ['id' => $messageId, 'platform_whatsapp_connection_id' => $connectionId, 'meta_message_id' => 'wamid.platform', 'purpose' => 'welcome']);
        $status = ['id' => 'wamid.platform', 'status' => 'delivered', 'timestamp' => (string) now()->timestamp];
        $value = ['metadata' => ['phone_number_id' => 'platform-phone'], 'statuses' => [$status]];
        $payload = json_encode(['entry' => [['changes' => [['value' => $value]]]]]);
        foreach (range(1, 2) as $_) $this->call('POST', '/api/v1/public/whatsapp/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $payload, 'app-secret')], $payload)->assertOk();
        $key = DB::table('platform_whatsapp_webhook_events')->value('event_key');
        (new ProcessPlatformWhatsAppWebhookEvent($key))->handle(app(WhatsAppStatusService::class));
        $this->assertDatabaseCount('platform_whatsapp_webhook_events', 1);
        $this->assertDatabaseHas('platform_whatsapp_messages', ['id' => $messageId, 'status' => 'delivered']);
        foreach (['read', 'sent'] as $state) {
            $status['status'] = $state;
            $value['statuses'] = [$status];
            $body = json_encode(['entry' => [['changes' => [['value' => $value]]]]]);
            $this->call('POST', '/api/v1/public/whatsapp/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'app-secret')], $body)->assertOk();
            (new ProcessPlatformWhatsAppWebhookEvent(DB::table('platform_whatsapp_webhook_events')->latest('id')->value('event_key')))->handle(app(WhatsAppStatusService::class));
        }
        $this->assertDatabaseHas('platform_whatsapp_messages', ['id' => $messageId, 'status' => 'read']);
        $this->getJson('/api/v1/platform/whatsapp/messages')->assertOk()->assertJsonCount(1, 'data.data')->assertDontSee('Owner')->assertDontSee('platform-private-token');
    }

    public function test_platform_and_business_connection_and_history_cannot_cross(): void
    {
        $superRole = PlatformRole::where('slug', 'super-administrator')->value('id');
        $newPermissions = DB::table('platform_permissions')->where('name', 'like', 'platform_whatsapp.%')->pluck('id');
        $this->assertSame(0, DB::table('platform_permission_role')->where('platform_role_id', '!=', $superRole)->whereIn('platform_permission_id', $newPermissions)->count());
        $this->admin();
        $this->setupConnection();
        $actor = User::factory()->create();
        $plan = Plan::create(['name' => 'WA clinic', 'branch_limit' => 2, 'member_limit' => 10, 'trial_days' => 14, 'features' => ['whatsapp_notifications' => true]]);
        $tenant = app(PlatformService::class)->createTenant(['name' => 'Clinic', 'slug' => 'clinic-platform-test', 'business_type_id' => BusinessType::where('slug', 'clinic')->value('id'), 'timezone' => 'Africa/Nairobi', 'plan_id' => $plan->id, 'owner_name' => 'Owner', 'owner_email' => 'clinic-platform@test.local', 'owner_password' => 'SecurePass12345'], $actor->id);
        $owner = User::where('email', 'clinic-platform@test.local')->firstOrFail();
        $this->flushSession(); $this->app['auth']->forgetGuards();
        $this->withHeader('Origin', 'http://localhost')->actingAs($owner, 'web')->postJson('/api/v1/session/clinic', ['clinic_id' => $tenant->id])->assertOk();
        $this->putJson('/api/v1/whatsapp/connection', ['business_account_id' => 'tenant-waba', 'phone_number_id' => 'platform-phone', 'access_token' => 'tenant-token'])->assertUnprocessable();
        $this->getJson('/api/v1/platform/whatsapp/templates')->assertForbidden();
        $this->getJson('/api/v1/platform/whatsapp/messages')->assertForbidden();
        $this->getJson('/api/v1/whatsapp/templates')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/whatsapp/messages')->assertOk()->assertJsonCount(0, 'data.data');
        $unprivileged = User::factory()->create(['is_platform_admin' => true]);
        $this->flushSession(); $this->app['auth']->forgetGuards();
        $this->actingAs($unprivileged, 'web')->getJson('/api/v1/platform/whatsapp/messages')->assertForbidden();
    }
}

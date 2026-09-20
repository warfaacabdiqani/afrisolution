<?php

namespace Tests\Feature;

use App\Jobs\{ProcessWhatsAppWebhookEvent, SendWhatsAppTemplateMessage};
use App\Models\{BusinessType, Plan, User};
use App\Services\{MetaWhatsAppClient, PlatformService, SystemSettingsService, WhatsAppStatusService};
use Database\Seeders\BusinessTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Http, Queue};
use Tests\TestCase;

class WhatsAppMessagingTest extends TestCase
{
    use RefreshDatabase;

    private array $metaTemplates = [];
    private array $sendResponse = ['messages' => [['id' => 'wamid.sent1']]];
    private int $sendStatus = 200;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BusinessTypeSeeder::class);
        config(['services.whatsapp.graph_version' => 'v24.0']);
        app(SystemSettingsService::class)->setSection('notifications', ['whatsapp_enabled' => true]);
        Http::fake(function ($request) {
            if (str_contains($request->url(), '/message_templates')) return Http::response(['data' => $this->metaTemplates], 200);
            if (str_contains($request->url(), '/messages')) return Http::response($this->sendResponse, $this->sendStatus);
            return Http::response([], 404);
        });
    }

    private function business(string $slug, bool $feature = true): array
    {
        $actor = User::factory()->create();
        $plan = Plan::create(['name' => 'WA '.$slug, 'branch_limit' => 2, 'member_limit' => 10, 'trial_days' => 14, 'features' => ['whatsapp_notifications' => $feature]]);
        $tenant = app(PlatformService::class)->createTenant(['name' => $slug, 'slug' => $slug, 'business_type_id' => BusinessType::where('slug', $slug)->value('id'), 'timezone' => 'Africa/Nairobi', 'plan_id' => $plan->id, 'owner_name' => 'Owner', 'owner_email' => $slug.'@stage2.test', 'owner_password' => 'SecurePass12345'], $actor->id);
        return [$tenant, User::where('email', $slug.'@stage2.test')->firstOrFail(), $plan];
    }

    private function select(User $user, int $tenant): void
    {
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->withHeader('Origin', 'http://localhost')->actingAs($user, 'web')->postJson('/api/v1/session/clinic', ['clinic_id' => $tenant])->assertOk();
    }

    private function connect(string $number): void
    {
        $this->putJson('/api/v1/whatsapp/connection', ['business_account_id' => '998877', 'phone_number_id' => $number, 'access_token' => 'secret-token-'.$number])->assertOk();
    }

    private function templates(array $rows): void
    {
        $this->metaTemplates = $rows;
        $this->postJson('/api/v1/whatsapp/templates/sync')->assertOk();
    }

    private function template(string $name = 'hello', string $status = 'APPROVED'): array
    {
        return ['id' => 'meta-'.$name, 'name' => $name, 'language' => 'en_US', 'category' => 'UTILITY', 'status' => $status, 'components' => [['type' => 'BODY', 'text' => 'Hello {{1}}']]];
    }

    public function test_sync_updates_meta_status_and_never_exposes_other_tenant_templates(): void
    {
        [$clinic, $owner] = $this->business('clinic');
        $this->select($owner, $clinic->id); $this->connect('111111111');
        $this->templates([$this->template(), $this->template('pending', 'PENDING')]);
        $this->getJson('/api/v1/whatsapp/templates')->assertOk()->assertJsonCount(2, 'data')->assertSee('PENDING');
        $this->templates([$this->template('hello', 'REJECTED')]);
        $this->assertDatabaseHas('whatsapp_templates', ['tenant_id' => $clinic->id, 'name' => 'hello', 'status' => 'REJECTED']);
        $this->assertDatabaseHas('whatsapp_templates', ['tenant_id' => $clinic->id, 'name' => 'pending', 'is_available' => false]);
        [$salon, $salonOwner] = $this->business('beauty-salon');
        $this->select($salonOwner, $salon->id); $this->connect('222222222');
        $this->getJson('/api/v1/whatsapp/templates')->assertOk()->assertJsonCount(0, 'data');
        $this->templates([$this->template('salon')]);
        $this->getJson('/api/v1/whatsapp/templates')->assertOk()->assertJsonCount(1, 'data')->assertSee('salon')->assertDontSee('pending');
    }

    public function test_approved_template_queues_safe_message_and_job_sends_with_server_credential(): void
    {
        [$clinic, $owner] = $this->business('clinic');
        $this->select($owner, $clinic->id); $this->connect('111111111'); $this->templates([$this->template()]);
        $templateId = DB::table('whatsapp_templates')->where('tenant_id', $clinic->id)->value('id');
        Queue::fake();
        $response = $this->postJson('/api/v1/whatsapp/messages', ['recipient' => '+252 61 123 4567', 'template_id' => $templateId, 'parameters' => ['body' => ['Amina']]])->assertAccepted()->assertJsonPath('data.status', 'queued')->assertDontSee('secret-token-111111111');
        $messageId = $response->json('data.id');
        $this->assertDatabaseHas('whatsapp_messages', ['id' => $messageId, 'tenant_id' => $clinic->id, 'recipient' => '+252611234567', 'status' => 'queued']);
        Queue::assertPushed(SendWhatsAppTemplateMessage::class, function ($job) use ($messageId) { $this->assertSame($messageId, $job->messageId); $this->assertStringNotContainsString('secret-token', serialize($job)); return true; });
        (new SendWhatsAppTemplateMessage($clinic->id, $messageId))->handle(app(MetaWhatsAppClient::class));
        Http::assertSent(fn ($request) => $request->method() === 'POST' && $request->hasHeader('Authorization', 'Bearer secret-token-111111111') && $request['to'] === '252611234567' && $request['template']['name'] === 'hello');
        $this->assertDatabaseHas('whatsapp_messages', ['id' => $messageId, 'meta_message_id' => 'wamid.sent1', 'status' => 'queued']);
        $this->getJson('/api/v1/whatsapp/messages/'.$messageId)->assertOk()->assertJsonPath('data.recipient', '+252611234567')->assertDontSee('Amina')->assertDontSee('secret-token');
    }

    public function test_rejects_unapproved_foreign_templates_and_bad_recipients(): void
    {
        [$clinic, $owner] = $this->business('clinic');
        $this->select($owner, $clinic->id); $this->connect('111111111'); $this->templates([$this->template('pending', 'PENDING')]);
        $pending = DB::table('whatsapp_templates')->where('tenant_id', $clinic->id)->value('id');
        $this->postJson('/api/v1/whatsapp/messages', ['recipient' => '+252611234567', 'template_id' => $pending, 'parameters' => ['body' => ['Amina']]])->assertUnprocessable();
        $this->templates([$this->template('approved')]);
        $approved = DB::table('whatsapp_templates')->where('tenant_id', $clinic->id)->where('name', 'approved')->value('id');
        $this->postJson('/api/v1/whatsapp/messages', ['recipient' => '611234567', 'template_id' => $approved, 'parameters' => ['body' => ['Amina']]])->assertUnprocessable()->assertJsonValidationErrors('recipient');
        [$salon, $salonOwner] = $this->business('beauty-salon');
        $this->select($salonOwner, $salon->id); $this->connect('222222222'); $this->templates([$this->template('salon')]);
        $foreign = DB::table('whatsapp_templates')->where('tenant_id', $salon->id)->value('id');
        $this->select($owner, $clinic->id);
        $this->postJson('/api/v1/whatsapp/messages', ['recipient' => '+252611234567', 'template_id' => $foreign, 'parameters' => ['body' => ['Amina']]])->assertUnprocessable();
        $this->getJson('/api/v1/whatsapp/messages/999999')->assertNotFound();
        $this->getJson('/api/v1/whatsapp/templates')->assertDontSee('salon');
        $this->assertDatabaseCount('whatsapp_messages', 0);
    }

    public function test_meta_failures_are_isolated_and_retries_are_bounded(): void
    {
        [$clinic, $owner] = $this->business('clinic');
        $this->select($owner, $clinic->id); $this->connect('111111111'); $this->templates([$this->template()]);
        $templateId = DB::table('whatsapp_templates')->value('id');
        Queue::fake();
        $messageId = $this->postJson('/api/v1/whatsapp/messages', ['recipient' => '+252611234567', 'template_id' => $templateId, 'parameters' => ['body' => ['Amina']]])->assertAccepted()->json('data.id');
        $this->sendResponse = ['error' => ['code' => 131026, 'message' => 'Sensitive provider details']];
        $this->sendStatus = 400;
        (new SendWhatsAppTemplateMessage($clinic->id, $messageId))->handle(app(MetaWhatsAppClient::class));
        $this->assertDatabaseHas('whatsapp_messages', ['id' => $messageId, 'status' => 'failed', 'failure_code' => '131026']);
        $this->assertStringNotContainsString('Sensitive provider details', json_encode(DB::table('whatsapp_messages')->where('id', $messageId)->first()));

        $retryId = $this->postJson('/api/v1/whatsapp/messages', ['recipient' => '+252611234567', 'template_id' => $templateId, 'parameters' => ['body' => ['Amina']]])->assertAccepted()->json('data.id');
        $this->sendStatus = 429;
        $job = new SendWhatsAppTemplateMessage($clinic->id, $retryId);
        $this->assertSame(4, $job->tries);
        $this->assertSame([60, 300, 900], $job->backoff());
        try { $job->handle(app(MetaWhatsAppClient::class)); $this->fail('Rate limiting should be retried.'); }
        catch (\App\Services\MetaWhatsAppException $e) { $this->assertTrue($e->temporary); }
        $this->assertDatabaseHas('whatsapp_messages', ['id' => $retryId, 'status' => 'queued', 'failure_code' => '131026']);
        $job->failed(new \App\Services\MetaWhatsAppException('131026', true));
        $this->assertDatabaseHas('whatsapp_messages', ['id' => $retryId, 'status' => 'failed']);
    }

    public function test_webhook_status_progression_is_idempotent_and_never_downgrades_or_crosses_tenant(): void
    {
        [$clinic, $owner] = $this->business('clinic');
        $this->select($owner, $clinic->id); $this->connect('111111111'); $this->templates([$this->template()]);
        $templateId = DB::table('whatsapp_templates')->where('tenant_id', $clinic->id)->value('id');
        Queue::fake();
        $messageId = $this->postJson('/api/v1/whatsapp/messages', ['recipient' => '+252611234567', 'template_id' => $templateId, 'parameters' => ['body' => ['Amina']]])->assertAccepted()->json('data.id');
        DB::table('whatsapp_messages')->where('id', $messageId)->update(['meta_message_id' => 'wamid.status1', 'status' => 'sent', 'sent_at' => now()]);
        app(SystemSettingsService::class)->setSection('notifications', ['whatsapp_secret' => 'app-secret']);
        foreach (['delivered', 'read', 'delivered', 'failed'] as $status) {
            $payload = json_encode(['entry' => [['changes' => [['value' => ['metadata' => ['phone_number_id' => '111111111'], 'statuses' => [['id' => 'wamid.status1', 'status' => $status, 'timestamp' => (string) now()->timestamp, 'errors' => [['code' => 131026, 'title' => 'Unsafe raw reason']]]]]]]]]]);
            $this->call('POST', '/api/v1/public/whatsapp/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $payload, 'app-secret')], $payload)->assertOk();
            $key = DB::table('whatsapp_webhook_events')->latest('id')->value('event_key');
            (new ProcessWhatsAppWebhookEvent($clinic->id, $key))->handle(app(WhatsAppStatusService::class));
        }
        $this->assertDatabaseHas('whatsapp_messages', ['id' => $messageId, 'status' => 'read']);
        $row = DB::table('whatsapp_messages')->where('id', $messageId)->first();
        $this->assertNotNull($row->sent_at); $this->assertNotNull($row->delivered_at); $this->assertNotNull($row->read_at);
        $this->assertNull($row->failed_at);
        $this->assertStringNotContainsString('Unsafe raw reason', json_encode($row));
        $this->assertDatabaseCount('whatsapp_webhook_events', 3);
    }

    public function test_history_permissions_plan_and_tenant_scope(): void
    {
        [$clinic, $owner, $plan] = $this->business('clinic');
        $this->select($owner, $clinic->id); $this->connect('111111111'); $this->templates([$this->template()]);
        $templateId = DB::table('whatsapp_templates')->value('id');
        Queue::fake();
        $id = $this->postJson('/api/v1/whatsapp/messages', ['recipient' => '+252611234567', 'template_id' => $templateId, 'parameters' => ['body' => ['Amina']]])->assertAccepted()->json('data.id');
        [$salon, $salonOwner] = $this->business('beauty-salon');
        $this->select($salonOwner, $salon->id); $this->connect('222222222');
        $this->getJson('/api/v1/whatsapp/messages')->assertOk()->assertJsonCount(0, 'data.data');
        $this->getJson('/api/v1/whatsapp/messages/'.$id)->assertNotFound();
        $this->select($owner, $clinic->id);
        DB::table('tenant_memberships')->where('tenant_id', $clinic->id)->update(['permissions' => json_encode(['whatsapp.view'])]);
        $this->getJson('/api/v1/whatsapp/messages')->assertOk()->assertJsonCount(1, 'data.data');
        $this->postJson('/api/v1/whatsapp/templates/sync')->assertForbidden();
        $this->postJson('/api/v1/whatsapp/messages', ['recipient' => '+252611234567', 'template_id' => $templateId])->assertForbidden();
        $plan->update(['features' => ['whatsapp_notifications' => false]]);
        $this->getJson('/api/v1/whatsapp/messages')->assertForbidden();
    }

    public function test_failed_webhook_records_safe_code_and_wrong_tenant_phone_cannot_change_message(): void
    {
        [$clinic, $owner] = $this->business('clinic');
        $this->select($owner, $clinic->id); $this->connect('111111111'); $this->templates([$this->template()]);
        $templateId = DB::table('whatsapp_templates')->value('id');
        Queue::fake();
        $id = $this->postJson('/api/v1/whatsapp/messages', ['recipient' => '+252611234567', 'template_id' => $templateId, 'parameters' => ['body' => ['Amina']]])->assertAccepted()->json('data.id');
        DB::table('whatsapp_messages')->where('id', $id)->update(['meta_message_id' => 'wamid.failed']);
        [$salon, $salonOwner] = $this->business('beauty-salon');
        $this->select($salonOwner, $salon->id); $this->connect('222222222');
        app(SystemSettingsService::class)->setSection('notifications', ['whatsapp_secret' => 'app-secret']);
        foreach ([['222222222', $salon->id], ['111111111', $clinic->id]] as [$phone, $tenant]) {
            $payload = json_encode(['entry' => [['changes' => [['value' => ['metadata' => ['phone_number_id' => $phone], 'statuses' => [['id' => 'wamid.failed', 'status' => 'failed', 'timestamp' => (string) now()->timestamp, 'errors' => [['code' => 131026, 'title' => 'Private provider reason']]]]]]]]]]);
            $this->call('POST', '/api/v1/public/whatsapp/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $payload, 'app-secret')], $payload)->assertOk();
            $key = DB::table('whatsapp_webhook_events')->where('tenant_id', $tenant)->latest('id')->value('event_key');
            (new ProcessWhatsAppWebhookEvent($tenant, $key))->handle(app(WhatsAppStatusService::class));
            $this->assertDatabaseHas('whatsapp_messages', ['id' => $id, 'status' => $tenant === $salon->id ? 'queued' : 'failed']);
        }
        $this->assertDatabaseHas('whatsapp_messages', ['id' => $id, 'failure_code' => '131026']);
        $this->assertStringNotContainsString('Private provider reason', json_encode(DB::table('whatsapp_messages')->where('id', $id)->first()));
    }
}

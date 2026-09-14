<?php

namespace Tests\Feature;

use App\Models\BusinessType;
use App\Models\Plan;
use App\Models\PlatformRole;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PlatformService;
use App\Services\SupportTicketService;
use App\Support\SupportTicketOptions as Options;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SupportTicketManagementTest extends TestCase
{
    use RefreshDatabase;

    private const PLATFORM = '/api/v1/platform/support-tickets';
    private const TENANT = '/api/v1/clinic/support';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Origin', 'http://localhost');
        Storage::fake('local');
    }

    private function admin(?array $permissions = null): User
    {
        $user = User::factory()->create(['is_platform_admin' => true]);
        if ($permissions === null) {
            $role = PlatformRole::where('slug', 'super-administrator')->firstOrFail();
        } else {
            $role = PlatformRole::create(['name' => 'Support '.$user->id, 'slug' => 'support-'.$user->id]);
            $role->permissions()->sync(DB::table('platform_permissions')->whereIn('name', $permissions)->pluck('id'));
        }
        $user->platformRoles()->sync([$role->id]);
        return $user;
    }

    private function business(string $slug, string $type = 'clinic'): array
    {
        $actor = User::factory()->create();
        $businessType = BusinessType::firstOrCreate(['slug' => $type], ['name' => ucwords(str_replace('-', ' ', $type)), 'category' => 'Services', 'status' => 'active']);
        $plan = Plan::create(['name' => 'Support plan '.$slug, 'branch_limit' => 2, 'member_limit' => 5, 'trial_days' => 14, 'features' => ['multi_branch' => true]]);
        $tenant = app(PlatformService::class)->createTenant([
            'name' => 'Business '.$slug, 'slug' => $slug, 'business_type_id' => $businessType->id,
            'timezone' => 'Africa/Nairobi', 'plan_id' => $plan->id,
            'owner_name' => 'Owner '.$slug, 'owner_email' => $slug.'@example.test', 'owner_password' => 'TestPassword123',
        ], $actor->id);
        return [$tenant, User::where('email', $slug.'@example.test')->firstOrFail()];
    }

    private function asUser(User $user): static
    {
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        return $this->actingAs($user, 'web');
    }

    private function select(Tenant $tenant, User $user): void
    {
        $this->asUser($user)->postJson('/api/v1/session/clinic', ['clinic_id' => $tenant->id])->assertOk();
    }

    private function payload(array $overrides = []): array
    {
        return array_replace(['subject' => 'Printing issue', 'category' => 'Technical Issue', 'priority' => 'Normal', 'description' => 'The print button does not work.'], $overrides);
    }

    private function ticket(Tenant $tenant, User $owner, array $overrides = []): int
    {
        return app(SupportTicketService::class)->create($tenant->id, DB::table('branches')->where('tenant_id', $tenant->id)->value('id'), $owner, $this->payload($overrides), null)['ticket_id'];
    }

    public function test_creation_uses_unique_numbers_across_businesses_and_preserves_tenant_workflow(): void
    {
        [$a, $ownerA] = $this->business('alpha');
        [$b, $ownerB] = $this->business('beta', 'beauty-salon');
        $this->select($a, $ownerA);
        $first = $this->postJson(self::TENANT.'/tickets', $this->payload(['tenant_id' => $b->id, 'user_id' => $ownerB->id, 'status' => Options::CLOSED]))->assertCreated()->json('data');
        $this->assertDatabaseHas('support_tickets', ['id' => $first['ticket_id'], 'tenant_id' => $a->id, 'user_id' => $ownerA->id, 'status' => Options::OPEN]);
        $this->getJson(self::TENANT.'/articles')->assertOk();
        $this->getJson(self::TENANT.'/faqs')->assertOk();
        $this->getJson(self::TENANT.'/search?q=appointment')->assertOk();
        $slug = config('support.articles')[0]['slug'];
        $this->getJson(self::TENANT.'/articles/'.$slug)->assertOk();
        $this->getJson(self::TENANT.'/system-info')->assertOk()->assertJsonPath('data.ticket_options.statuses', Options::STATUSES);
        $this->postJson(self::TENANT.'/tickets/'.$first['ticket_id'].'/reply', ['message' => 'Additional details', 'source' => Options::PLATFORM])->assertCreated();
        $this->getJson(self::TENANT.'/tickets/'.$first['ticket_id'])->assertOk()->assertJsonCount(2, 'data.messages')->assertJsonPath('data.messages.1.source', Options::BUSINESS);
        $this->select($b, $ownerB);
        $second = $this->postJson(self::TENANT.'/tickets', $this->payload())->assertCreated()->json('data');
        $this->assertNotSame($first['ticket_number'], $second['ticket_number']);
        $this->getJson(self::TENANT.'/tickets')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $second['ticket_id']);
    }

    public function test_platform_lists_all_businesses_filters_searches_and_reports_global_stats(): void
    {
        [$a, $ownerA] = $this->business('alpha');
        [$b, $ownerB] = $this->business('beta', 'future-business');
        $first = $this->ticket($a, $ownerA);
        $second = $this->ticket($b, $ownerB, ['subject' => 'Booking access', 'priority' => 'Urgent', 'category' => 'Account / Access']);
        DB::table('support_tickets')->where('id', $second)->update(['status' => Options::WAITING, 'created_at' => '2026-09-01 10:00:00']);
        DB::table('support_tickets')->where('id', $first)->update(['created_at' => '2026-08-01 10:00:00']);
        $this->asUser($this->admin());
        $this->getJson(self::PLATFORM)->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson(self::PLATFORM.'/stats')->assertOk()->assertJsonPath('data.total', 2)->assertJsonPath('data.open', 1)->assertJsonPath('data.waiting', 1);
        $this->getJson(self::PLATFORM.'/options')->assertOk()->assertJsonPath('data.statuses', Options::STATUSES);
        foreach ([['tenant_id' => $b->id], ['business_type_id' => $b->business_type_id], ['status' => Options::WAITING], ['priority' => 'Urgent'], ['category' => 'Account / Access'], ['date_from' => '2026-09-01', 'date_to' => '2026-09-01'], ['date_to' => '2026-08-01']] as $filter) {
            $response = $this->getJson(self::PLATFORM.'?'.http_build_query($filter))->assertOk()->assertJsonPath('meta.total', 1);
            $response->assertJsonPath('data.0.id', isset($filter['date_to']) && $filter['date_to'] === '2026-08-01' ? $first : $second);
        }
        foreach (['Booking', 'Business beta', 'Owner beta', 'beta@example.test', DB::table('support_tickets')->where('id', $second)->value('ticket_number')] as $term) {
            $this->getJson(self::PLATFORM.'?'.http_build_query(['search' => $term]))->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $second);
        }
        $this->getJson(self::PLATFORM.'?search=missing')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson(self::PLATFORM.'?status=Invalid')->assertUnprocessable();
        $this->getJson(self::PLATFORM.'?date_from=2026-09-10&date_to=2026-09-01')->assertUnprocessable();
        $this->getJson(self::PLATFORM.'/stats?tenant_id='.$b->id)->assertOk()->assertJsonPath('data.total', 2);
    }

    public function test_detail_contains_business_branch_diagnostics_and_shared_messages(): void
    {
        [$tenant, $owner] = $this->business('sports', 'stadium');
        $id = $this->ticket($tenant, $owner, ['current_page' => '/app/bookings', 'steps_to_reproduce' => 'Click Print', 'expected_result' => 'Printout', 'actual_result' => 'Nothing']);
        $this->asUser($this->admin())->getJson(self::PLATFORM.'/'.$id)->assertOk()
            ->assertJsonPath('data.ticket.tenant_name', $tenant->name)
            ->assertJsonPath('data.ticket.business_type_name', $tenant->businessType->name)
            ->assertJsonPath('data.ticket.branch_name', DB::table('branches')->where('tenant_id', $tenant->id)->value('name'))
            ->assertJsonPath('data.ticket.user_role', 'owner')->assertJsonPath('data.ticket.steps_to_reproduce', 'Click Print')
            ->assertJsonPath('data.ticket.current_page', '/app/bookings')->assertJsonPath('data.messages.0.source', Options::BUSINESS);
        $this->getJson(self::PLATFORM.'/999999')->assertNotFound();
    }

    public function test_admin_reply_is_visible_to_tenant_with_stable_sender_identity_and_audit(): void
    {
        [$tenant, $owner] = $this->business('alpha');
        $id = $this->ticket($tenant, $owner);
        $admin = $this->admin();
        $this->asUser($admin)->postJson(self::PLATFORM.'/'.$id.'/reply', ['message' => 'We are reviewing the issue.', 'source' => Options::BUSINESS])->assertCreated();
        $this->assertDatabaseCount('support_ticket_messages', 2);
        $this->assertDatabaseHas('platform_audit_logs', ['actor_id' => $admin->id, 'tenant_id' => $tenant->id, 'action' => 'support_ticket.admin_replied', 'module' => 'Support Tickets']);
        $audit = DB::table('platform_audit_logs')->where('action', 'support_ticket.admin_replied')->first();
        $this->assertStringNotContainsString('reviewing', $audit->metadata);
        $admin->forceFill(['is_platform_admin' => false])->save();
        $this->select($tenant, $owner);
        $this->getJson(self::TENANT.'/tickets/'.$id)->assertOk()->assertJsonPath('data.messages.1.message', 'We are reviewing the issue.')
            ->assertJsonPath('data.messages.1.source', Options::PLATFORM)->assertJsonPath('data.messages.1.user_name', $admin->name);
    }

    public function test_status_priority_category_updates_sync_and_closed_tickets_require_reopening(): void
    {
        [$tenant, $owner] = $this->business('alpha');
        $id = $this->ticket($tenant, $owner);
        $admin = $this->admin();
        foreach (Options::STATUSES as $status) {
            $this->asUser($admin)->putJson(self::PLATFORM.'/'.$id.'/status', ['status' => $status])->assertNoContent();
            $this->select($tenant, $owner);
            $this->getJson(self::TENANT.'/tickets/'.$id)->assertOk()->assertJsonPath('data.ticket.status', $status);
        }
        $this->postJson(self::TENANT.'/tickets/'.$id.'/reply', ['message' => 'Reopen'])->assertUnprocessable();
        $this->asUser($admin)->postJson(self::PLATFORM.'/'.$id.'/reply', ['message' => 'Reopen'])->assertUnprocessable();
        $this->putJson(self::PLATFORM.'/'.$id.'/status', ['status' => Options::OPEN])->assertNoContent();
        foreach (Options::PRIORITIES as $priority) $this->putJson(self::PLATFORM.'/'.$id.'/priority', ['priority' => $priority])->assertNoContent();
        $this->putJson(self::PLATFORM.'/'.$id.'/category', ['category' => 'Bug Report'])->assertNoContent();
        $this->select($tenant, $owner);
        $this->getJson(self::TENANT.'/tickets/'.$id)->assertOk()->assertJsonPath('data.ticket.priority', 'Urgent')->assertJsonPath('data.ticket.category', 'Bug Report');
        $this->postJson(self::TENANT.'/tickets/'.$id.'/reply', ['message' => 'Thank you'])->assertCreated();
        foreach (['status_changed', 'priority_changed', 'category_changed', 'closed'] as $event) {
            $this->assertDatabaseHas('platform_audit_logs', ['actor_id' => $admin->id, 'tenant_id' => $tenant->id, 'action' => 'support_ticket.'.$event]);
        }
        $audit = DB::table('platform_audit_logs')->where('action', 'support_ticket.category_changed')->first();
        $this->assertSame(['category' => 'Technical Issue'], json_decode($audit->old_values, true));
        $this->assertSame(['category' => 'Bug Report'], json_decode($audit->new_values, true));
    }

    public function test_tenant_isolation_covers_details_replies_uploads_and_downloads(): void
    {
        [$a, $ownerA] = $this->business('alpha');
        [$b, $ownerB] = $this->business('beta');
        $first = $this->ticket($a, $ownerA);
        $second = $this->ticket($b, $ownerB);
        $attachment = app(SupportTicketService::class)->upload($second, $ownerB, UploadedFile::fake()->create('report.pdf', 20, 'application/pdf'));
        $this->select($a, $ownerA);
        $this->getJson(self::TENANT.'/tickets?tenant_id='.$b->id)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $first);
        $this->getJson(self::TENANT.'/tickets/'.$second)->assertNotFound();
        $this->postJson(self::TENANT.'/tickets/'.$second.'/reply', ['message' => 'Intrusion', 'tenant_id' => $b->id])->assertNotFound();
        $this->postJson(self::TENANT.'/tickets/'.$second.'/attachments', ['attachment' => UploadedFile::fake()->create('report.pdf', 20, 'application/pdf')])->assertNotFound();
        $this->getJson(self::TENANT.'/tickets/'.$second.'/attachments/'.$attachment.'/download')->assertNotFound();
        $this->getJson(self::TENANT.'/tickets/'.$first.'/attachments/'.$attachment.'/download')->assertNotFound();
        $this->withHeader('X-Clinic-Context', (string) $b->id)->getJson(self::TENANT.'/tickets')->assertStatus(409);
    }

    public function test_tenant_own_ticket_permissions_are_preserved(): void
    {
        [$tenant, $owner] = $this->business('alpha');
        $id = $this->ticket($tenant, $owner);
        $staff = User::factory()->create();
        DB::table('tenant_memberships')->insert(['tenant_id' => $tenant->id, 'user_id' => $staff->id, 'role' => 'staff', 'status' => 'active', 'all_branches' => true]);
        $this->select($tenant, $staff);
        $this->getJson(self::TENANT.'/tickets')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson(self::TENANT.'/tickets/'.$id)->assertNotFound();
        $this->postJson(self::TENANT.'/tickets/'.$id.'/reply', ['message' => 'Not mine'])->assertForbidden();
        DB::table('tenant_memberships')->where('user_id', $staff->id)->update(['permissions' => '[]']);
        $this->getJson(self::TENANT.'/tickets')->assertForbidden();
    }

    public function test_platform_endpoints_reject_guests_business_owners_and_unprivileged_admins(): void
    {
        $this->getJson(self::PLATFORM)->assertUnauthorized();
        [$tenant, $owner] = $this->business('alpha');
        $id = $this->ticket($tenant, $owner);
        foreach ([$owner, $this->admin([])] as $user) {
            $this->asUser($user);
            foreach (['', '/stats', '/options', '/'.$id, '/'.$id.'/attachments/1/download'] as $suffix) $this->getJson(self::PLATFORM.$suffix)->assertForbidden();
            $this->postJson(self::PLATFORM.'/'.$id.'/reply', ['message' => 'Denied'])->assertForbidden();
            foreach (['status' => Options::RESOLVED, 'priority' => 'High', 'category' => 'Other'] as $field => $value) $this->putJson(self::PLATFORM.'/'.$id.'/'.$field, [$field => $value])->assertForbidden();
        }
        $inactive = $this->admin(); $inactive->forceFill(['status' => 'inactive'])->save();
        $this->asUser($inactive)->getJson(self::PLATFORM)->assertForbidden();
        $this->getJson('/api/v1/session')->assertOk()->assertJsonPath('data.platform_permissions', []);
    }

    public function test_action_permissions_are_independent_and_cannot_bypass_close_permission(): void
    {
        [$tenant, $owner] = $this->business('alpha');
        $id = $this->ticket($tenant, $owner);
        $reader = $this->admin(['support_tickets.view']);
        $this->asUser($reader)->getJson(self::PLATFORM)->assertOk();
        $this->getJson(self::PLATFORM.'/options')->assertOk();
        $this->postJson(self::PLATFORM.'/'.$id.'/reply', ['message' => 'Denied'])->assertForbidden();
        $this->putJson(self::PLATFORM.'/'.$id.'/priority', ['priority' => 'High'])->assertForbidden();
        $this->putJson(self::PLATFORM.'/'.$id.'/status', ['status' => Options::RESOLVED])->assertForbidden();
        $editor = $this->admin(['support_tickets.view', 'support_tickets.update', 'support_tickets.reply']);
        $this->asUser($editor)->putJson(self::PLATFORM.'/'.$id.'/status', ['status' => Options::RESOLVED])->assertNoContent();
        $this->putJson(self::PLATFORM.'/'.$id.'/status', ['status' => Options::CLOSED])->assertForbidden();
        $this->putJson(self::PLATFORM.'/'.$id.'/priority', ['priority' => 'Urgent'])->assertForbidden();
        DB::table('support_tickets')->where('id', $id)->update(['status' => Options::CLOSED]);
        $this->putJson(self::PLATFORM.'/'.$id.'/status', ['status' => Options::OPEN])->assertForbidden();
        $this->postJson(self::PLATFORM.'/'.$id.'/reply', ['message' => 'Bypass'])->assertUnprocessable();
        $priorityAdmin = $this->admin(['support_tickets.view', 'support_tickets.manage_priority']);
        $this->asUser($priorityAdmin)->putJson(self::PLATFORM.'/'.$id.'/priority', ['priority' => 'High'])->assertNoContent();
        $this->putJson(self::PLATFORM.'/'.$id.'/category', ['category' => 'Other'])->assertForbidden();
    }

    public function test_attachments_are_private_authorized_and_linked_to_shared_admin_reply(): void
    {
        [$tenant, $owner] = $this->business('alpha');
        $this->select($tenant, $owner);
        $id = $this->postJson(self::TENANT.'/tickets', $this->payload(['attachment' => UploadedFile::fake()->create('issue.pdf', 10, 'application/pdf')]))->assertCreated()->json('data.ticket_id');
        $first = DB::table('support_ticket_attachments')->first();
        Storage::disk('local')->assertExists($first->file_path);
        $this->get(self::TENANT.'/tickets/'.$id.'/attachments/'.$first->id.'/download')->assertOk()->assertDownload('issue.pdf');
        $this->postJson(self::TENANT.'/tickets/'.$id.'/attachments', ['attachment' => UploadedFile::fake()->create('followup.pdf', 10, 'application/pdf')])->assertCreated();
        $this->asUser($this->admin(['support_tickets.view']))->getJson(self::PLATFORM.'/'.$id.'/attachments/'.$first->id.'/download')->assertForbidden();
        $this->asUser($this->admin())->get(self::PLATFORM.'/'.$id.'/attachments/'.$first->id.'/download')->assertOk()->assertDownload('issue.pdf');
        $message = $this->postJson(self::PLATFORM.'/'.$id.'/reply', ['message' => 'Please read the guide.', 'attachment' => UploadedFile::fake()->create('guide.pdf', 10, 'application/pdf')])->assertCreated()->json('data.message_id');
        $this->select($tenant, $owner);
        $response = $this->getJson(self::TENANT.'/tickets/'.$id)->assertOk()->assertJsonCount(3, 'data.attachments');
        $response->assertJsonPath('data.attachments.2.message_id', $message)->assertJsonMissingPath('data.attachments.2.file_path');
        $guide = DB::table('support_ticket_attachments')->where('message_id', $message)->first();
        $this->get(self::TENANT.'/tickets/'.$id.'/attachments/'.$guide->id.'/download')->assertOk()->assertDownload('guide.pdf');
        $this->get('/storage/'.$guide->file_path)->assertForbidden();
    }

    public function test_invalid_inputs_are_rejected_and_server_errors_are_safe_and_atomic(): void
    {
        [$tenant, $owner] = $this->business('alpha');
        $id = $this->ticket($tenant, $owner);
        $this->asUser($this->admin());
        foreach (['status', 'priority', 'category'] as $field) $this->putJson(self::PLATFORM.'/'.$id.'/'.$field, [$field => 'Invalid'])->assertUnprocessable();
        $this->postJson(self::PLATFORM.'/'.$id.'/reply', ['message' => '   '])->assertUnprocessable();
        $this->postJson(self::PLATFORM.'/'.$id.'/reply', ['message' => str_repeat('x', 3001)])->assertUnprocessable();
        $this->postJson(self::PLATFORM.'/'.$id.'/reply', ['message' => 'Attachment', 'attachment' => UploadedFile::fake()->create('bad.exe', 10)])->assertUnprocessable();
        $this->postJson(self::PLATFORM.'/'.$id.'/reply', ['message' => 'Attachment', 'attachment' => UploadedFile::fake()->create('big.pdf', 2049, 'application/pdf')])->assertUnprocessable();
        $this->mock(PlatformService::class)->shouldReceive('audit')->andThrow(new \RuntimeException('SQLSTATE secret database credentials'));
        config(['app.debug' => true]);
        $this->postJson(self::PLATFORM.'/'.$id.'/reply', ['message' => 'Must roll back'])->assertStatus(500)->assertExactJson(['message' => 'Unable to complete the support request. Please try again.']);
        $this->assertDatabaseCount('support_ticket_messages', 1);
        $this->assertDatabaseHas('support_tickets', ['id' => $id, 'status' => Options::OPEN]);
    }

    public function test_pagination_and_all_status_counts_include_closed_tickets(): void
    {
        [$tenant, $owner] = $this->business('alpha');
        foreach (range(0, 20) as $i) {
            $id = $this->ticket($tenant, $owner);
            DB::table('support_tickets')->where('id', $id)->update(['status' => Options::STATUSES[$i % 5]]);
        }
        $this->asUser($this->admin())->getJson(self::PLATFORM)->assertOk()->assertJsonCount(20, 'data')->assertJsonPath('meta.last_page', 2);
        $this->getJson(self::PLATFORM.'?page=2')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson(self::PLATFORM.'/stats')->assertOk()->assertExactJson(['data' => ['open' => 5, 'in_progress' => 4, 'waiting' => 4, 'resolved' => 4, 'closed' => 4, 'total' => 21]]);
    }
}

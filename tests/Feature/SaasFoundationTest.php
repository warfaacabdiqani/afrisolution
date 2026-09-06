<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PlatformService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SaasFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Origin', 'http://localhost');
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->is_platform_admin = true;
        $u->save();

        return $u;
    }

    private function clinic(User $admin, string $slug = 'alpha'): Tenant
    {
        $plan = Plan::create(['name' => 'Starter', 'branch_limit' => 2, 'member_limit' => 2, 'trial_days' => 14]);

        return app(PlatformService::class)->createTenant(['name' => $slug, 'slug' => $slug, 'timezone' => 'Africa/Nairobi', 'plan_id' => $plan->id, 'owner_name' => 'Owner', 'owner_email' => $slug.'@example.test', 'owner_password' => 'SecurePass12345'], $admin->id);
    }

    public function test_login_session_and_logout(): void
    {
        $u = User::factory()->create(['password' => Hash::make('SecurePass12345')]);
        $this->postJson('/login', ['email' => $u->email, 'password' => 'wrong'])->assertUnprocessable();
        $this->postJson('/login', ['email' => $u->email, 'password' => 'SecurePass12345'])->assertOk()->assertJsonPath('data.id', $u->id);
        $this->getJson('/api/v1/session')->assertOk()->assertJsonMissingPath('data.password');
        $this->postJson('/logout')->assertNoContent();
        $this->assertGuest('web');
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/login', ['email' => 'nobody@example.test', 'password' => 'wrong'])->assertUnprocessable();
        }
        $this->postJson('/login', ['email' => 'nobody@example.test', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_platform_requires_admin_and_does_not_allow_mass_assignment(): void
    {
        $this->getJson('/api/v1/platform/tenants')->assertUnauthorized();
        $u = User::factory()->create();
        $this->actingAs($u)->getJson('/api/v1/platform/tenants')->assertForbidden();
        $u->fill(['is_platform_admin' => true]);
        $this->assertFalse((bool) $u->is_platform_admin);
    }

    public function test_onboarding_creates_complete_tenant_and_audit_atomically(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $plan = Plan::create(['name' => 'Starter', 'branch_limit' => 1, 'member_limit' => 1, 'trial_days' => 14]);
        $data = ['name' => 'Clinic', 'slug' => 'clinic', 'timezone' => 'Africa/Nairobi', 'plan_id' => $plan->id, 'owner_name' => 'Owner', 'owner_email' => 'owner@example.test', 'owner_password' => 'SecurePass12345', 'owner_password_confirmation' => 'SecurePass12345'];
        $this->postJson('/api/v1/platform/tenants', $data + ['tenant_id' => 999])->assertUnprocessable();
        $this->assertDatabaseCount('tenants', 0);
        $id = $this->postJson('/api/v1/platform/tenants', $data)->assertCreated()->json('data.id');
        $this->assertDatabaseHas('branches', ['tenant_id' => $id, 'name' => 'Main branch']);
        $this->assertDatabaseHas('subscriptions', ['tenant_id' => $id, 'status' => 'trial']);
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'tenant.created', 'subject_id' => $id]);
        $this->postJson('/api/v1/platform/tenants', array_merge($data, ['slug' => 'another']))->assertUnprocessable();
        $this->assertDatabaseCount('tenants', 1);
    }

    public function test_cross_tenant_access_selection_and_missing_context_are_denied(): void
    {
        $admin = $this->admin();
        $a = $this->clinic($admin);
        $b = $this->clinic($admin, 'beta');
        $owner = User::where('email', 'alpha@example.test')->firstOrFail();
        $otherBranch = DB::table('branches')->where('tenant_id', $b->id)->value('id');
        $this->actingAs($owner)->getJson('/api/v1/clinic/branches')->assertForbidden();
        $this->postJson('/api/v1/session/clinic', ['clinic_id' => $b->id])->assertForbidden();
        $this->postJson('/api/v1/session/clinic', ['clinic_id' => $a->id, 'tenant_id' => $b->id])->assertUnprocessable();
        $this->postJson('/api/v1/session/clinic', ['clinic_id' => $a->id])->assertOk();
        $this->getJson('/api/v1/clinic/branches')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/clinic/branches/'.$otherBranch)->assertNotFound();
    }

    public function test_platform_admin_has_no_automatic_clinic_access(): void
    {
        $admin = $this->admin();
        $t = $this->clinic($admin);
        $this->actingAs($admin)->withSession(['tenant_id' => $t->id])->getJson('/api/v1/clinic/branches')->assertForbidden();
    }

    public function test_authorized_switch_changes_scope_and_rejects_stale_tabs(): void
    {
        $admin = $this->admin();
        $a = $this->clinic($admin);
        $b = $this->clinic($admin, 'beta');
        $owner = User::where('email', 'alpha@example.test')->firstOrFail();
        DB::table('tenant_memberships')->insert(['tenant_id' => $b->id, 'user_id' => $owner->id, 'role' => 'staff', 'status' => 'active']);
        $this->actingAs($owner)->withSession(['tenant_id' => $a->id]);
        $this->postJson('/api/v1/session/clinic', ['clinic_id' => $b->id])->assertOk()->assertJsonPath('data.active_tenant_id', $b->id);
        $this->withHeader('X-Clinic-Context', (string) $a->id)->getJson('/api/v1/clinic/branches')->assertStatus(409);
        $branch = DB::table('branches')->where('tenant_id', $b->id)->value('id');
        $this->withHeader('X-Clinic-Context', (string) $b->id)->getJson('/api/v1/clinic/branches')->assertOk()->assertJsonPath('data.0.id', $branch);
    }

    public function test_csrf_is_required_for_login_and_stateful_mutations(): void
    {
        $this->app->instance('env', 'local');
        $this->postJson('/login', ['email' => 'test@example.test', 'password' => 'anything'])->assertStatus(419);
        $this->postJson('/api/v1/session/clinic', ['clinic_id' => 1])->assertStatus(419);
    }

    public function test_suspension_revocation_and_expiry_apply_to_existing_sessions(): void
    {
        $admin = $this->admin();
        $t = $this->clinic($admin);
        $owner = User::where('email', 'alpha@example.test')->firstOrFail();
        $this->actingAs($owner)->withSession(['tenant_id' => $t->id]);
        $t->update(['status' => 'suspended']);
        $this->getJson('/api/v1/clinic/branches')->assertForbidden();
        $t->update(['status' => 'active']);
        DB::table('tenant_memberships')->where('tenant_id', $t->id)->update(['status' => 'suspended']);
        $this->getJson('/api/v1/clinic/branches')->assertForbidden();
        DB::table('tenant_memberships')->where('tenant_id', $t->id)->update(['status' => 'active']);
        DB::table('subscriptions')->where('tenant_id', $t->id)->update(['trial_ends_at' => now()->subMinute()]);
        $this->getJson('/api/v1/clinic/branches')->assertForbidden();
    }

    public function test_model_scope_fails_closed_and_assigns_server_ownership(): void
    {
        $admin = $this->admin();
        $t = $this->clinic($admin);
        $context = app(TenantContext::class);
        $context->set($t->id);
        $branch = new Branch(['name' => 'Second']);
        $branch->tenant_id = 999;
        $branch->save();
        $this->assertEquals($t->id, $branch->tenant_id);
        $this->assertEquals(2, Branch::count());
        $context->clear();
        $this->expectException(HttpException::class);
        Branch::count();
    }

    public function test_branch_owner_cannot_be_changed(): void
    {
        $admin = $this->admin();
        $t = $this->clinic($admin);
        app(TenantContext::class)->set($t->id);
        $branch = Branch::firstOrFail();
        $this->expectException(HttpException::class);
        $branch->tenant_id = 999;
        $branch->save();
    }

    public function test_limits_last_owner_and_cross_tenant_membership_updates(): void
    {
        $admin = $this->admin();
        $t = $this->clinic($admin);
        $b = $this->clinic($admin, 'beta');
        $this->actingAs($admin);
        $root = '/api/v1/platform/tenants/'.$t->id;
        $this->postJson($root.'/branches', ['name' => 'Second'])->assertCreated();
        $this->postJson($root.'/branches', ['name' => 'Third'])->assertUnprocessable();
        $this->postJson($root.'/branches', ['name' => 'Forged', 'tenant_id' => $b->id])->assertUnprocessable();
        $member = DB::table('tenant_memberships')->where('tenant_id', $t->id)->value('id');
        $this->patchJson($root.'/members/'.$member, ['role' => 'staff', 'status' => 'active'])->assertUnprocessable();
        $other = DB::table('tenant_memberships')->where('tenant_id', $b->id)->value('id');
        $this->patchJson($root.'/members/'.$other, ['role' => 'staff', 'status' => 'active'])->assertNotFound();
        $payload = ['name' => 'Staff', 'email' => 'staff@example.test', 'password' => 'SecurePass12345', 'password_confirmation' => 'SecurePass12345', 'role' => 'staff'];
        $this->postJson($root.'/members', $payload)->assertCreated();
        $this->postJson($root.'/members', array_merge($payload, ['email' => 'extra@example.test']))->assertUnprocessable();
        $this->assertDatabaseMissing('users', ['email' => 'extra@example.test']);
        $small = Plan::create(['name' => 'Small', 'branch_limit' => 1, 'member_limit' => 1, 'trial_days' => 7]);
        $this->putJson($root.'/subscription', ['plan_id' => $small->id, 'status' => 'active'])->assertUnprocessable();
    }

    public function test_subscription_changes_and_audits(): void
    {
        $admin = $this->admin();
        $t = $this->clinic($admin);
        $this->actingAs($admin);
        $plan = Plan::first();
        $this->putJson('/api/v1/platform/tenants/'.$t->id.'/subscription', ['plan_id' => $plan->id, 'status' => 'trial', 'trial_ends_at' => now()->addDays(30)->toISOString()])->assertOk();
        $this->putJson('/api/v1/platform/tenants/'.$t->id.'/subscription', ['plan_id' => $plan->id, 'status' => 'cancelled'])->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->getJson('/api/v1/platform/audits')->assertOk()->assertJsonPath('data.0.action', 'subscription.updated');
    }

    public function test_dashboard_reports_real_platform_data(): void
    {
        $admin = $this->admin();
        $this->clinic($admin);
        $this->actingAs($admin)->getJson('/api/v1/platform/dashboard')
            ->assertOk()
            ->assertJsonPath('data.stats.total_clinics', 1)
            ->assertJsonPath('data.stats.active_clinics', 1)
            ->assertJsonPath('data.stats.trial_clinics', 1)
            ->assertJsonPath('data.stats.total_members', 1)
            ->assertJsonCount(1, 'data.recent_activity');
    }

    public function test_tenant_list_and_detail_include_real_management_summary(): void
    {
        $admin = $this->admin();
        $tenant = $this->clinic($admin);
        $this->actingAs($admin)->getJson('/api/v1/platform/tenants')
            ->assertOk()
            ->assertJsonPath('data.0.owner.email', 'alpha@example.test')
            ->assertJsonPath('data.0.plan_name', 'Starter')
            ->assertJsonPath('data.0.members_count', 1)
            ->assertJsonPath('data.0.branches_count', 1);
        $this->getJson('/api/v1/platform/tenants/'.$tenant->id)
            ->assertOk()
            ->assertJsonPath('data.slug', 'alpha');
        $this->getJson('/api/v1/platform/tenants/'.$tenant->id.'/audits')
            ->assertOk()
            ->assertJsonPath('data.0.action', 'tenant.created');
    }
}

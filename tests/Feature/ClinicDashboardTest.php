<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PlatformService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClinicDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function clinic(string $slug = 'alpha'): array
    {
        $actor = User::factory()->create();
        $plan = Plan::create(['name' => 'Professional', 'branch_limit' => 3, 'member_limit' => 10, 'trial_days' => 14, 'features' => ['multi_branch' => true, 'patient_management' => true, 'appointments' => true]]);
        $tenant = app(PlatformService::class)->createTenant(['name' => $slug, 'slug' => $slug, 'timezone' => 'Africa/Nairobi', 'plan_id' => $plan->id, 'owner_name' => 'Clinic Owner', 'owner_email' => $slug.'@example.test', 'owner_password' => 'SecurePass12345'], $actor->id);
        return [$tenant, User::where('email', $slug.'@example.test')->firstOrFail(), $plan];
    }

    private function select(Tenant $tenant, User $user): void
    {
        $this->withHeader('Origin', 'http://localhost')->actingAs($user)->postJson('/api/v1/session/clinic', ['clinic_id' => $tenant->id])->assertOk();
    }

    public function test_dashboard_requires_authentication_membership_and_permission(): void
    {
        $this->getJson('/api/v1/clinic/dashboard')->assertUnauthorized();
        [$tenant, $owner] = $this->clinic();
        $this->select($tenant, $owner);
        $this->getJson('/api/v1/clinic/dashboard')->assertOk()->assertJsonPath('data.stats.total_patients', 0)->assertJsonPath('data.recent_patients', []);
        $this->getJson('/api/v1/platform/dashboard')->assertForbidden();
        DB::table('tenant_memberships')->where('tenant_id', $tenant->id)->update(['permissions' => '[]']);
        $this->getJson('/api/v1/clinic/dashboard')->assertForbidden();
        DB::table('tenant_memberships')->where('tenant_id', $tenant->id)->update(['status' => 'suspended']);
        $this->getJson('/api/v1/clinic/context')->assertForbidden();
        $this->getJson('/api/v1/clinic/dashboard')->assertForbidden();
    }

    public function test_tenant_and_branch_access_are_enforced_and_counts_are_scoped(): void
    {
        [$a, $owner] = $this->clinic();
        [$b] = $this->clinic('beta');
        $main = DB::table('branches')->where('tenant_id', $a->id)->value('id');
        $other = DB::table('branches')->insertGetId(['tenant_id' => $a->id, 'name' => 'Second']);
        $foreign = DB::table('branches')->where('tenant_id', $b->id)->value('id');
        foreach ([[$a->id, $main], [$a->id, $other], [$b->id, $foreign]] as [$tenant, $branch]) {
            $doctor = User::factory()->create();
            $membership = DB::table('tenant_memberships')->insertGetId(['tenant_id' => $tenant, 'user_id' => $doctor->id, 'role' => 'doctor', 'status' => 'active', 'all_branches' => false]);
            DB::table('branch_memberships')->insert(['tenant_id' => $tenant, 'membership_id' => $membership, 'branch_id' => $branch]);
        }
        $this->select($a, $owner);
        $this->getJson('/api/v1/clinic/dashboard?tenant_id='.$b->id)->assertOk()->assertJsonPath('data.stats.active_doctors', 1)->assertJsonPath('data.staff_count', 2);
        $this->postJson('/api/v1/clinic/branch', ['branch_id' => $foreign])->assertForbidden();
        $this->postJson('/api/v1/clinic/branch', ['branch_id' => $other])->assertOk()->assertJsonPath('data.branch.id', $other);
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'branch.switched', 'tenant_id' => $a->id, 'actor_id' => $owner->id]);
        $this->withHeader('X-Branch-Context', (string) $main)->getJson('/api/v1/clinic/dashboard')->assertStatus(409);
        $this->flushHeaders(); $this->withHeader('Origin', 'http://localhost');
        $member = DB::table('tenant_memberships')->where('user_id', $owner->id)->first();
        DB::table('tenant_memberships')->where('id', $member->id)->update(['all_branches' => false]);
        DB::table('branch_memberships')->insert(['tenant_id' => $a->id, 'membership_id' => $member->id, 'branch_id' => $main]);
        $this->getJson('/api/v1/clinic/context')->assertOk()->assertJsonCount(1, 'data.branches')->assertJsonPath('data.branch.id', $main);
        $this->postJson('/api/v1/clinic/branch', ['branch_id' => $other])->assertForbidden();
        $this->getJson('/api/v1/clinic/branches/'.$other)->assertForbidden();
        $this->getJson('/api/v1/clinic/branches/'.$foreign)->assertNotFound();
        $this->postJson('/api/v1/session/clinic', ['clinic_id' => $b->id])->assertForbidden();
    }

    public function test_features_action_permissions_and_single_branch_plan_are_enforced(): void
    {
        [$tenant, $owner, $plan] = $this->clinic();
        $this->select($tenant, $owner);
        $this->getJson('/api/v1/clinic/modules/pharmacy')->assertForbidden();
        $this->getJson('/api/v1/clinic/modules/patients?action=create')->assertOk()->assertJsonPath('data.available', true);
        DB::table('tenant_memberships')->where('user_id', $owner->id)->update(['permissions' => json_encode(['dashboard.view', 'patients.view'])]);
        $this->getJson('/api/v1/clinic/modules/patients')->assertOk();
        $this->getJson('/api/v1/clinic/modules/patients?action=create')->assertForbidden();
        $other = DB::table('branches')->insertGetId(['tenant_id' => $tenant->id, 'name' => 'Second']);
        $plan->update(['features' => ['multi_branch' => false]]);
        $this->postJson('/api/v1/clinic/branch', ['branch_id' => $other])->assertForbidden();
        $this->getJson('/api/v1/clinic/context')->assertOk()->assertJsonCount(1, 'data.branches');
    }

    public function test_subscription_restrictions_preserve_context_for_restriction_screen(): void
    {
        [$tenant, $owner] = $this->clinic();
        $this->select($tenant, $owner);
        foreach (['expired', 'suspended', 'cancelled'] as $status) {
            DB::table('subscriptions')->where('tenant_id', $tenant->id)->update(['status' => $status]);
            $this->getJson('/api/v1/clinic/context')->assertOk()->assertJsonPath('data.operational', false);
            $this->getJson('/api/v1/clinic/dashboard')->assertForbidden();
            $this->getJson('/api/v1/clinic/modules/patients')->assertForbidden();
        }
        DB::table('subscriptions')->where('tenant_id', $tenant->id)->update(['status' => 'trial', 'trial_ends_at' => now()->subDay()]);
        $this->getJson('/api/v1/clinic/dashboard')->assertForbidden();
        DB::table('subscriptions')->where('tenant_id', $tenant->id)->update(['status' => 'active']);
        $this->getJson('/api/v1/clinic/dashboard')->assertOk();
    }

    public function test_doctor_limit_is_enforced_when_assigning_clinic_roles(): void
    {
        [$tenant, $owner, $plan] = $this->clinic();
        $plan->update(['doctor_limit' => 0]);
        $membership = DB::table('tenant_memberships')->where('user_id', $owner->id)->value('id');
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(\App\Services\TenantProvisioningService::class)->updateMember($tenant, $membership, ['role' => 'doctor', 'status' => 'active'], $owner->id);
    }
}

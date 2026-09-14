<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Plan;
use App\Models\PlatformRole;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PlatformService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        $u->platformRoles()->sync([PlatformRole::where('slug', 'super-administrator')->value('id')]);

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
        $this->assertDatabaseHas('branches', ['tenant_id' => $id, 'name' => 'Main Branch']);
        $this->assertDatabaseHas('subscriptions', ['tenant_id' => $id, 'status' => 'trial']);
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'tenant.created', 'subject_id' => $id]);
        $this->postJson('/api/v1/platform/tenants', array_merge($data, ['slug' => 'another']))->assertUnprocessable();
        $this->assertDatabaseCount('tenants', 1);
    }

    public function test_new_tenants_default_to_clinic_business_type_in_api_and_context(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $plan = Plan::create(['name' => 'Starter', 'branch_limit' => 1, 'member_limit' => 1, 'trial_days' => 14]);
        $data = ['name' => 'Clinic', 'slug' => 'clinic-default', 'timezone' => 'Africa/Nairobi', 'plan_id' => $plan->id, 'owner_name' => 'Owner', 'owner_email' => 'owner@example.test', 'owner_password' => 'SecurePass12345', 'owner_password_confirmation' => 'SecurePass12345'];

        $response = $this->postJson('/api/v1/platform/tenants', $data);
        $tenantId = $response->json('data.id');

        $response->assertCreated()
            ->assertJsonPath('data.business_type.slug', 'clinic');

        $this->assertDatabaseHas('tenants', ['id' => $tenantId, 'business_type_id' => DB::table('business_types')->where('slug', 'clinic')->value('id')]);
        $this->assertSame('clinic', Tenant::findOrFail($tenantId)->businessType->slug);
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
            ->assertJsonCount(2, 'data.recent_activity');
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
            ->assertJsonPath('data.slug', $tenant->slug);
        $this->getJson('/api/v1/platform/tenants/'.$tenant->id.'/audits')
            ->assertOk()
            ->assertJsonFragment(['action' => 'tenant.created'])
            ->assertJsonFragment(['action' => 'tenant.business_code.generated']);
    }

    public function test_platform_plan_management_works_without_tenant_context_and_preserves_subscription_snapshots(): void
    {
        $admin = $this->admin();
        $tenant = $this->clinic($admin);
        $this->actingAs($admin);
        $payload = [
            'name' => 'Professional', 'slug' => 'professional', 'description' => 'Complete clinic management.',
            'status' => 'active', 'price' => 49, 'currency' => 'usd', 'billing_period' => 'monthly',
            'trial_days' => 14, 'branch_limit' => 3, 'member_limit' => 15, 'doctor_limit' => 10,
            'patient_limit' => 5000, 'storage_limit_gb' => 10, 'appointment_limit' => null,
            'invoice_limit' => null, 'features' => ['patient_management' => true, 'api_access' => false],
        ];
        $id = $this->postJson('/api/v1/platform/plans', $payload)->assertCreated()->json('data.id');
        $this->getJson('/api/v1/platform/plans')->assertOk()->assertJsonPath('data.1.name', 'Professional');
        $this->getJson('/api/v1/platform/plans/'.$id)->assertOk()->assertJsonPath('data.features.patient_management', true);
        $this->putJson('/api/v1/platform/plans/'.$id, array_merge($payload, ['branch_limit' => 9]))
            ->assertOk()->assertJsonPath('data.branch_limit', 9);
        $this->assertDatabaseHas('subscriptions', ['tenant_id' => $tenant->id, 'branch_limit' => 2]);
        $this->getJson('/api/v1/platform/plans/'.$id.'/audits')->assertOk()->assertJsonPath('data.0.action', 'plan.updated');
        $subscribedPlan = DB::table('subscriptions')->where('tenant_id', $tenant->id)->value('plan_id');
        $this->deleteJson('/api/v1/platform/plans/'.$subscribedPlan)->assertUnprocessable();
        $this->assertDatabaseHas('plans', ['id' => $subscribedPlan]);
        $this->deleteJson('/api/v1/platform/plans/'.$id)->assertNoContent();
        $this->assertDatabaseMissing('plans', ['id' => $id]);
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'plan.deleted', 'subject_id' => $id]);

        $normal = User::factory()->create();
        $this->actingAs($normal)->getJson('/api/v1/platform/plans')->assertForbidden();
        $this->postJson('/api/v1/platform/plans', $payload)->assertForbidden();
    }

    public function test_platform_subscription_index_uses_real_data_filters_and_authorization(): void
    {
        $admin = $this->admin();
        $tenant = $this->clinic($admin);
        $plan = Plan::firstOrFail();
        $this->actingAs($admin)->getJson('/api/v1/platform/subscriptions')
            ->assertOk()
            ->assertJsonPath('stats.total', 1)
            ->assertJsonPath('stats.trial', 1)
            ->assertJsonPath('data.0.tenant_name', 'alpha')
            ->assertJsonPath('data.0.plan_name', 'Starter')
            ->assertJsonPath('data.0.members_count', 1)
            ->assertJsonPath('data.0.branches_count', 1);
        $this->getJson('/api/v1/platform/subscriptions?status=active')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/platform/subscriptions?plan_id='.$plan->id.'&search=alpha')->assertOk()->assertJsonCount(1, 'data');

        $normal = User::factory()->create();
        $this->actingAs($normal)->getJson('/api/v1/platform/subscriptions')->assertForbidden();
    }

    public function test_platform_administrators_roles_and_permissions_are_managed_safely(): void
    {
        $admin=$this->admin(); $this->actingAs($admin);
        $roleId=$this->postJson('/api/v1/platform/roles',['name'=>'Billing Manager','slug'=>'billing-manager','description'=>'Manages subscriptions.','permissions'=>['subscriptions.view','subscriptions.manage']])->assertCreated()->json('data.id');
        $userId=$this->postJson('/api/v1/platform/users',['name'=>'Billing Admin','email'=>'billing@example.test','status'=>'active','role_ids'=>[$roleId],'password'=>'SecurePass12345','password_confirmation'=>'SecurePass12345'])->assertCreated()->json('data.id');
        $this->getJson('/api/v1/platform/users')->assertOk()->assertJsonPath('data.data.0.email','billing@example.test');
        $this->getJson('/api/v1/platform/roles')->assertOk();
        $this->getJson('/api/v1/platform/permissions')->assertOk()->assertJsonFragment(['name'=>'users.manage']);
        $this->putJson('/api/v1/platform/users/'.$admin->id,['name'=>$admin->name,'email'=>$admin->email,'status'=>'inactive','role_ids'=>[PlatformRole::where('slug','super-administrator')->value('id')]])->assertForbidden();
        $billing=User::findOrFail($userId);
        $this->flushSession();
        $this->actingAs($billing)->getJson('/api/v1/platform/users')->assertForbidden();
        $this->flushSession();
        $this->actingAs($billing)->getJson('/api/v1/platform/subscriptions')->assertOk();
        $this->flushSession();
        $this->actingAs($billing)->getJson('/api/v1/platform/plans')->assertForbidden();
    }

    public function test_audit_log_is_filtered_exportable_immutable_and_redacts_secrets(): void
    {
        $admin = $this->admin();
        $tenant = $this->clinic($admin);
        $this->actingAs($admin);

        app(PlatformService::class)->audit(
            $admin->id,
            'security.checked',
            'tenant',
            $tenant->id,
            ['password' => 'plain-text', 'nested' => ['api_key' => 'secret-key'], 'safe' => 'visible'],
            ['token' => 'old-token'],
            ['token' => 'new-token'],
        );

        $response = $this->getJson('/api/v1/platform/audits?action=security.checked&tenant_id='.$tenant->id.'&search='.$tenant->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.action', 'security.checked')
            ->assertJsonPath('data.0.metadata.password', '[REDACTED]')
            ->assertJsonPath('data.0.metadata.nested.api_key', '[REDACTED]')
            ->assertJsonPath('data.0.metadata.safe', 'visible')
            ->assertJsonPath('data.0.old_values.token', '[REDACTED]')
            ->assertJsonPath('data.0.new_values.token', '[REDACTED]');
        $this->assertSame($admin->email, $response->json('data.0.actor_email'));
        $this->assertSame($tenant->name, $response->json('data.0.tenant_name'));

        $this->get('/api/v1/platform/audits/export?action=security.checked')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->putJson('/api/v1/platform/audits/1', [])->assertNotFound();
        $this->deleteJson('/api/v1/platform/audits/1')->assertNotFound();

        $normal = User::factory()->create();
        $this->actingAs($normal)->getJson('/api/v1/platform/audits')->assertForbidden();
    }

    public function test_system_settings_persist_branding_encrypt_secrets_and_are_authorized(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $this->actingAs($admin)->getJson('/api/v1/platform/settings')
            ->assertOk()->assertJsonPath('data.general.platform_name','Afri Clinic');

        $general = ['platform_name'=>'Afri Health','platform_url'=>'https://afri.example','support_email'=>'support@afri.example','support_phone'=>'+252 61 0000000','organization_name'=>'Afri Health Ltd','default_trial_days'=>21,'default_plan_id'=>null,'registration_enabled'=>true,'platform_status'=>'active'];
        $this->putJson('/api/v1/platform/settings/general',$general)
            ->assertOk()->assertJsonPath('data.platform_name','Afri Health');
        $this->putJson('/api/v1/platform/settings/general',array_merge($general,['support_email'=>'invalid']))->assertUnprocessable();

        $this->post('/api/v1/platform/settings/branding',['_method'=>'PUT','display_name'=>'Afri Health','footer_text'=>'Healthcare SaaS','logo'=>UploadedFile::fake()->image('logo.png',300,100)])
            ->assertOk()->assertJsonPath('data.display_name','Afri Health');
        $this->assertCount(1, Storage::disk('public')->files('branding'));

        $email = ['mailer'=>'smtp','smtp_host'=>'smtp.example.test','smtp_port'=>587,'smtp_username'=>'mailer','smtp_password'=>'top-secret-password','encryption'=>'tls','from_email'=>'mail@afri.example','from_name'=>'Afri Health'];
        $this->putJson('/api/v1/platform/settings/email',$email)->assertOk()->assertJsonPath('data.smtp_password','••••••••••');
        $stored = DB::table('system_settings')->where('key','email.smtp_password')->first();
        $this->assertTrue((bool)$stored->is_encrypted);
        $this->assertStringNotContainsString('top-secret-password',$stored->value);
        $this->getJson('/api/v1/platform/settings')->assertOk()->assertJsonPath('data.email.smtp_password','••••••••••');
        $this->getJson('/api/v1/public/settings')->assertOk()->assertJsonFragment(['general.platform_name'=>'Afri Health'])->assertJsonMissingPath('data.email.smtp_password');
        $audit = DB::table('platform_audit_logs')->where('action','settings.email.updated')->latest('id')->first();
        $this->assertStringNotContainsString('top-secret-password',(string)$audit->new_values);

        $this->postJson('/api/v1/platform/settings/maintenance',['enabled'=>true,'message'=>'Scheduled maintenance.'])->assertOk();
        $this->getJson('/api/v1/clinic/branches')->assertStatus(503)->assertJsonPath('message','Scheduled maintenance.');
        $this->postJson('/api/v1/platform/settings/maintenance',['enabled'=>false,'message'=>'Scheduled maintenance.'])->assertOk();
        $this->assertDatabaseHas('platform_audit_logs',['action'=>'maintenance.enabled']);
        $this->assertDatabaseHas('platform_audit_logs',['action'=>'maintenance.disabled']);

        $normal = User::factory()->create();
        $this->actingAs($normal)->getJson('/api/v1/platform/settings')->assertForbidden();
        $this->putJson('/api/v1/platform/settings/general',$general)->assertForbidden();
    }
}

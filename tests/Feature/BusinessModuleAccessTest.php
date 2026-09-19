<?php
namespace Tests\Feature;

use App\Models\{BusinessType, Plan, Tenant, User};
use App\Services\PlatformService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BusinessModuleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\BusinessTypeSeeder::class);
    }

    private function workspace(string $slug): array
    {
        $actor = User::factory()->create();
        $features = array_fill_keys(['patient_management', 'appointments', 'clinicians', 'emr', 'prescriptions', 'pharmacy', 'billing', 'basic_reports'], true);
        $plan = Plan::create(['name' => 'Complete', 'branch_limit' => 2, 'member_limit' => 10, 'trial_days' => 14, 'features' => $features]);
        $tenant = app(PlatformService::class)->createTenant(['name' => $slug, 'slug' => $slug, 'business_type_id' => BusinessType::where('slug', $slug)->value('id'), 'timezone' => 'Africa/Nairobi', 'plan_id' => $plan->id, 'owner_name' => 'Owner', 'owner_email' => $slug.'@test.example', 'owner_password' => 'SecurePass12345'], $actor->id);
        $owner = User::where('email', $slug.'@test.example')->firstOrFail();
        $this->flushSession(); $this->app['auth']->forgetGuards();
        $this->withHeader('Origin', 'http://localhost')->actingAs($owner, 'web')->postJson('/api/v1/session/clinic', ['clinic_id' => $tenant->id])->assertOk();
        return [$tenant, $owner, $plan];
    }

    public function test_clinic_and_dental_navigation_preserve_supported_healthcare_modules(): void
    {
        foreach (['clinic', 'dental'] as $slug) {
            $this->workspace($slug);
            $data = $this->getJson('/api/v1/clinic/context')->assertOk()->assertJsonPath('data.business_profile.settings_label', 'Clinic Settings')->json('data');
            $allowed = collect($data['modules'])->where('allowed', true)->pluck('key')->all();
            $expected = ['dashboard', 'patients', 'appointments', 'doctors', 'consultations', 'prescriptions', 'billing', 'reports', 'staff', 'support', 'settings'];
            if ($slug === 'clinic') array_splice($expected, 6, 0, ['pharmacy']);
            $this->assertSame($expected, $allowed);
            $this->getJson('/api/v1/clinic/prescriptions')->assertOk();
        }
    }

    public function test_non_healthcare_workspaces_block_clinical_apis_and_settings_even_with_all_features_and_permissions(): void
    {
        foreach (['beauty-salon' => ['Client', 'Salon Settings'], 'stadium' => ['Customer', 'Stadium Settings']] as $slug => [$label, $settings]) {
            $this->workspace($slug);
            $data = $this->getJson('/api/v1/clinic/context')->assertOk()->assertJsonPath('data.labels.customer', $label)->assertJsonPath('data.business_profile.settings_label', $settings)->json('data');
            $this->assertSame($slug==='beauty-salon'?['dashboard','appointments','billing','reports','staff','support','settings']:['dashboard', 'billing', 'reports', 'staff', 'support', 'settings'], collect($data['modules'])->where('allowed', true)->pluck('key')->all());
            foreach (['patients', 'appointments', 'doctors', 'consultations', 'prescriptions', 'pharmacy'] as $module) {
                $this->getJson('/api/v1/clinic/modules/'.$module)->assertForbidden()->assertJsonPath('code', 'BUSINESS_MODULE_UNAVAILABLE');
            }
            foreach (['prescriptions', 'doctors', 'patients', 'appointments', 'reports/clinical', 'reports/prescriptions'] as $path) {
                $this->getJson('/api/v1/clinic/'.$path)->assertForbidden()->assertJsonPath('code', 'BUSINESS_MODULE_UNAVAILABLE');
            }
            $sections = $this->getJson('/api/v1/clinic/settings')->assertOk()->json('data.sections');
            foreach (['patients', 'appointments', 'clinical', 'pharmacy'] as $section) $this->assertArrayNotHasKey($section, $sections);
            $this->getJson('/api/v1/clinic/support/articles')->assertOk()->assertJsonMissing(['slug' => 'create-prescription']);
            $this->getJson('/api/v1/clinic/dashboard')->assertOk()->assertJsonMissingPath('data.recent_patients')->assertJsonMissingPath('data.today_appointments')->assertJsonMissingPath('data.stats');
        }
    }

    public function test_access_denial_codes_keep_business_plan_permission_and_subscription_separate(): void
    {
        [$tenant, $owner, $plan] = $this->workspace('clinic');
        $features = $plan->features;
        $plan->update(['features' => array_replace($features, ['prescriptions' => false])]);
        $this->getJson('/api/v1/clinic/prescriptions')->assertForbidden()->assertJsonPath('code', 'PLAN_FEATURE_UNAVAILABLE');
        $plan->update(['features' => $features]);
        DB::table('tenant_memberships')->where('tenant_id', $tenant->id)->update(['permissions' => json_encode(['dashboard.view'])]);
        $this->getJson('/api/v1/clinic/prescriptions')->assertForbidden()->assertJsonPath('code', 'PERMISSION_DENIED');
        $tenant->update(['business_type_id' => BusinessType::where('slug', 'beauty-salon')->value('id')]);
        $this->getJson('/api/v1/clinic/prescriptions')->assertForbidden()->assertJsonPath('code', 'BUSINESS_MODULE_UNAVAILABLE');
        DB::table('subscriptions')->where('tenant_id', $tenant->id)->update(['status' => 'expired']);
        $this->getJson('/api/v1/clinic/prescriptions')->assertForbidden()->assertJsonPath('code', 'SUBSCRIPTION_INACTIVE');
    }

    public function test_tenant_switch_replaces_the_entire_business_profile(): void
    {
        [$clinic, $owner] = $this->workspace('clinic');
        [$salon] = $this->workspace('beauty-salon');
        [$stadium] = $this->workspace('stadium');
        foreach ([$salon, $stadium] as $tenant) DB::table('tenant_memberships')->insert(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active', 'all_branches' => true]);
        $this->flushSession(); $this->app['auth']->forgetGuards();
        $this->actingAs($owner, 'web');
        foreach ([[$clinic, 'Patient', 'clinic'], [$salon, 'Client', 'beauty-salon'], [$stadium, 'Customer', 'stadium'], [$clinic, 'Patient', 'clinic']] as [$tenant, $label, $profile]) {
            $this->postJson('/api/v1/session/clinic', ['clinic_id' => $tenant->id])->assertOk();
            $this->getJson('/api/v1/clinic/context')->assertOk()->assertJsonPath('data.clinic.id', $tenant->id)->assertJsonPath('data.labels.customer', $label)->assertJsonPath('data.navigation_profile_key', $profile);
            $this->getJson('/api/v1/clinic/dashboard')->assertOk()->assertJsonPath('data.profile', $profile)->assertJsonPath('data.business.id', $tenant->id);
        }
    }

    public function test_dashboard_profiles_use_real_shared_metrics_without_clinical_queries(): void
    {
        foreach (['beauty-salon' => 'Salon Information', 'stadium' => 'Stadium Information'] as $slug => $title) {
            $this->workspace($slug);
            DB::flushQueryLog(); DB::enableQueryLog();
            $data = $this->getJson('/api/v1/clinic/dashboard')->assertOk()->assertJsonPath('data.profile', $slug)->json('data');
            $queries = DB::getQueryLog(); DB::disableQueryLog();
            foreach ($queries as $query) $this->assertDoesNotMatchRegularExpression('/(?:from|join) ["`]?(patients|doctors|appointments|consultations|prescriptions)\b/i', $query['query']);
            $widgets = collect($data['widgets'])->keyBy('key');
            if ($slug==='beauty-salon') {
                $this->assertSame(['total_clients','today_appointments','active_stylists','monthly_revenue'],$widgets->keys()->all());
                $this->assertNull($widgets['total_clients']['value']); // Plan omits salon client access.
                $this->assertNull($widgets['active_stylists']['value']);
                $this->assertSame(0,$widgets['today_appointments']['value']);
                $this->assertEquals(0,$widgets['monthly_revenue']['value']);
                $this->assertTrue($widgets['monthly_revenue']['available']);
            } else {
                $this->assertSame(['staff_count', 'branch_count', 'subscription', 'monthly_revenue'], $widgets->keys()->all());
                $this->assertSame(1, $widgets['staff_count']['value']);
                $this->assertSame(1, $widgets['branch_count']['value']);
                $this->assertSame('Complete', $widgets['subscription']['value']);
                $this->assertNull($widgets['monthly_revenue']['value']);
                $this->assertFalse($widgets['monthly_revenue']['available']);
            }
            $this->assertSame(['business_information'], array_keys($data['sections']));
            $this->assertSame($title, $data['sections']['business_information']['title']);
            $this->assertSame($slug==='beauty-salon'?['book_appointment']:[], array_column($data['quick_actions'],'key'));
        }
    }

    public function test_healthcare_dashboard_generic_contract_and_quick_action_permissions(): void
    {
        foreach (['clinic', 'dental'] as $slug) {
            [$tenant] = $this->workspace($slug);
            $data = $this->getJson('/api/v1/clinic/dashboard')->assertOk()->assertJsonPath('data.profile', 'clinic')->json('data');
            $widgets = collect($data['widgets'])->keyBy('key');
            $this->assertSame(0, $widgets['total_patients']['value']);
            $this->assertSame(0, $widgets['today_appointments']['value']);
            $this->assertEquals(0, $widgets['monthly_revenue']['value']);
            $this->assertTrue($widgets['monthly_revenue']['available']);
            $this->assertSame(['add_patient', 'book_appointment', 'create_prescription'], array_column($data['quick_actions'], 'key'));
            DB::table('tenant_memberships')->where('tenant_id', $tenant->id)->update(['permissions' => json_encode(['dashboard.view', 'patients.view'])]);
            $data = $this->getJson('/api/v1/clinic/dashboard')->assertOk()->json('data');
            $this->assertSame([], $data['quick_actions']);
            $this->assertArrayNotHasKey('appointments', $data['sections']);
        }
    }
}

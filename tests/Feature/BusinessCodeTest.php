<?php

namespace Tests\Feature;

use App\Models\{BusinessType, Plan, PlatformRole, Tenant, User};
use App\Services\{BusinessCodeGenerator, PlatformService, SystemSettingsService};
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BusinessCodeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\BusinessTypeSeeder::class);
        $this->admin = User::factory()->create(['is_platform_admin' => true]);
        $this->admin->platformRoles()->sync([PlatformRole::where('slug', 'super-administrator')->value('id')]);
        $this->plan = Plan::create(['name' => 'Starter', 'branch_limit' => 2, 'member_limit' => 5, 'trial_days' => 14]);
        $this->withHeader('Origin', 'http://localhost')->actingAs($this->admin);
    }

    private function data(string $type = 'clinic', string $email = 'owner@example.test'): array
    {
        return ['name' => 'Business', 'timezone' => 'Africa/Nairobi', 'business_type_id' => BusinessType::where('slug', $type)->value('id'),
            'owner_name' => 'Owner', 'owner_email' => $email, 'owner_password' => 'SecurePass12345', 'owner_password_confirmation' => 'SecurePass12345', 'plan_id' => $this->plan->id];
    }

    private function general(array $values = []): array
    {
        return array_replace($this->getJson('/api/v1/platform/settings')->assertOk()->json('data.general'), $values);
    }

    public function test_all_types_share_sequence_and_ignore_submitted_codes(): void
    {
        $number = 200001;
        foreach (['clinic' => 'CLN', 'dental' => 'DEN', 'beauty-salon' => 'SAL', 'stadium' => 'STD'] as $type => $abbreviation) {
            $data = $this->data($type, $type.'@example.test') + ['slug' => '23', 'business_code' => 'FORGED', 'code_prefix' => 'HACK'];
            $this->postJson('/api/v1/platform/tenants', $data)->assertCreated()->assertJsonPath('data.slug', 'AFRI-'.$abbreviation.'-'.$number++);
        }
        $this->assertSame(4, Tenant::distinct()->count('slug'));
        $this->assertDatabaseHas('platform_sequences', ['key' => 'business_code', 'current_value' => 200004]);
        $this->assertSame(4, DB::table('platform_audit_logs')->where('action', 'tenant.business_code.generated')->count());
        $this->assertStringNotContainsString('SecurePass', DB::table('platform_audit_logs')->get()->toJson());
    }

    public function test_preview_is_permission_protected_and_does_not_reserve_codes(): void
    {
        foreach (['clinic' => 'CLN', 'dental' => 'DEN', 'beauty-salon' => 'SAL', 'stadium' => 'STD'] as $type => $code) {
            $id = BusinessType::where('slug', $type)->value('id');
            $this->getJson('/api/v1/platform/businesses/next-code?business_type_id='.$id)->assertOk()->assertJsonPath('code', 'AFRI-'.$code.'-200001');
        }
        $this->assertDatabaseHas('platform_sequences', ['current_value' => 0]);
        $this->getJson('/api/v1/platform/businesses/next-code?business_type_id=999999')->assertUnprocessable();
        $type = BusinessType::create(['name' => 'Future', 'slug' => 'future', 'category' => 'other', 'status' => 'active']);
        $this->assertSame('AFRI-BUS-200001', app(BusinessCodeGenerator::class)->preview($type));
        $type->update(['status' => 'inactive']);
        $this->getJson('/api/v1/platform/businesses/next-code?business_type_id='.$type->id)->assertUnprocessable();
        foreach ([false, true] as $isAdmin) {
            $this->actingAs(User::factory()->create(['is_platform_admin' => $isAdmin]));
            $this->getJson('/api/v1/platform/businesses/next-code?business_type_id=1')->assertForbidden();
            $this->getJson('/api/v1/platform/businesses/code-examples')->assertForbidden();
        }
    }

    public function test_settings_normalize_validate_and_never_rewind_the_active_counter(): void
    {
        app(SystemSettingsService::class)->setSection('general', ['code_contains' => '20']);
        $this->putJson('/api/v1/platform/settings/general', $this->general(['code_prefix' => ' Afri9 ', 'business_code_start' => 300001]))->assertOk()->assertJsonPath('data.code_prefix', 'AFRI9')->assertJsonPath('data.code_contains', '20');
        $this->postJson('/api/v1/platform/tenants', $this->data())->assertCreated()->assertJsonPath('data.slug', 'AFRI9-CLN-300001');
        $this->postJson('/api/v1/platform/tenants', $this->data('dental', 'second@example.test'))->assertCreated()->assertJsonPath('data.slug', 'AFRI9-DEN-300002');
        // An unchanged initialization setting remains saveable after the counter advances.
        $this->putJson('/api/v1/platform/settings/general', $this->general(['platform_name' => 'Updated']))->assertOk();
        $this->putJson('/api/v1/platform/settings/general', $this->general(['business_code_start' => 100]))->assertUnprocessable()->assertJsonPath('errors.business_code_start.0', 'Starting sequence cannot be lower than the current generated sequence.');
        foreach (['', 'AF RI', 'AF-RI', '<script>', str_repeat('A', 21), ['invalid']] as $prefix) {
            $this->putJson('/api/v1/platform/settings/general', $this->general(['code_prefix' => $prefix]))->assertUnprocessable()->assertJsonValidationErrors('code_prefix');
        }
        foreach ([0, -1, 1.5] as $start) {
            $this->putJson('/api/v1/platform/settings/general', $this->general(['business_code_start' => $start]))->assertUnprocessable()->assertJsonValidationErrors('business_code_start');
        }
        $this->putJson('/api/v1/platform/settings/general', $this->general(['code_prefix' => 'NEW', 'business_code_start' => 900000]))->assertOk();
        $this->postJson('/api/v1/platform/tenants', $this->data('stadium', 'third@example.test'))->assertCreated()->assertJsonPath('data.slug', 'NEW-STD-300003');
        $this->assertDatabaseHas('platform_audit_logs', ['action' => 'platform.business_code_settings.updated']);
        $this->getJson('/api/v1/platform/businesses/code-examples?code_prefix=draft&business_code_start=999999')->assertOk()->assertJsonFragment(['code' => 'DRAFT-SAL-300004']);
        $this->assertDatabaseHas('platform_sequences', ['current_value' => 300003]);
    }

    public function test_legacy_codes_are_untouched_and_collisions_are_skipped(): void
    {
        foreach (['23', '223', 'AFRI-CLN-200001'] as $code) Tenant::create(['name' => 'Legacy', 'slug' => $code, 'timezone' => 'Africa/Nairobi']);
        $codes = app(BusinessCodeGenerator::class);
        $this->assertSame('AFRI-CLN-200002', $codes->preview(BusinessType::where('slug', 'clinic')->firstOrFail()));
        $tenant = app(PlatformService::class)->createTenant($this->data() + ['slug' => '23'], $this->admin->id);
        $this->assertSame('AFRI-CLN-200002', $tenant->slug);
        foreach (['23', '223', 'AFRI-CLN-200001'] as $code) $this->assertDatabaseHas('tenants', ['name' => 'Legacy', 'slug' => $code]);
        $this->expectException(QueryException::class);
        Tenant::create(['name' => 'Duplicate', 'slug' => $tenant->slug, 'timezone' => 'Africa/Nairobi']);
    }

    public function test_failed_provisioning_rolls_back_code_and_audit(): void
    {
        User::factory()->create(['email' => 'owner@example.test']);
        try {
            app(PlatformService::class)->createTenant($this->data(), $this->admin->id);
            $this->fail('Duplicate owner must fail.');
        } catch (QueryException $exception) {
            $this->assertDatabaseCount('tenants', 0);
            $this->assertDatabaseHas('platform_sequences', ['current_value' => 0]);
            $this->assertDatabaseMissing('platform_audit_logs', ['action' => 'tenant.business_code.generated']);
        }
    }

    public function test_numeric_prefix_is_preserved_and_a_stale_preview_is_not_authoritative(): void
    {
        $this->putJson('/api/v1/platform/settings/general', $this->general(['code_prefix' => '0']))->assertOk();
        $type = BusinessType::where('slug', 'clinic')->firstOrFail();
        $stale = $this->getJson('/api/v1/platform/businesses/next-code?business_type_id='.$type->id)->assertOk()->json('code');
        $this->assertSame('0-CLN-200001', $stale);
        $this->postJson('/api/v1/platform/tenants', $this->data())->assertCreated()->assertJsonPath('data.slug', $stale);
        $this->postJson('/api/v1/platform/tenants', $this->data('clinic', 'other@example.test') + ['slug' => $stale])
            ->assertCreated()->assertJsonPath('data.slug', '0-CLN-200002');
    }
}

<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use App\Services\PlatformService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PatientManagementTest extends TestCase
{
    use RefreshDatabase;
    private const ROOT = '/api/v1/clinic/patients';
    private function clinic(string $slug = 'alpha'): array
    {
        $actor = User::factory()->create();
        $plan = Plan::create(['name' => 'Clinical', 'branch_limit' => 3, 'member_limit' => 10, 'trial_days' => 14, 'patient_limit' => 100, 'storage_limit_gb' => 1, 'features' => ['patient_management' => true, 'multi_branch' => true]]);
        $tenant = app(PlatformService::class)->createTenant(['name' => $slug, 'slug' => $slug, 'timezone' => 'Africa/Nairobi', 'plan_id' => $plan->id, 'owner_name' => 'Owner', 'owner_email' => $slug.'@example.test', 'owner_password' => 'SecurePass12345'], $actor->id);
        // A legacy tenant retains its patient-number prefix after auto-generation launches.
        $tenant->update(['slug' => $slug]);
        $owner = User::where('email', $slug.'@example.test')->firstOrFail();
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->withHeader('Origin', 'http://localhost')->actingAs($owner, 'web')->postJson('/api/v1/session/clinic', ['clinic_id' => $tenant->id])->assertOk();
        return [$tenant, $owner, $plan];
    }
    private function data(array $extra = []): array { return array_merge(['first_name' => 'Amina', 'middle_name' => 'Noor', 'last_name' => 'Hassan', 'gender' => 'female', 'date_of_birth' => '1992-04-15', 'phone' => '+252 612 345 678', 'email' => 'amina@example.test'], $extra); }
    private function create(array $extra = []): int { return $this->postJson(self::ROOT, $this->data($extra))->assertCreated()->json('data.id'); }

    public function test_registration_validation_numbering_duplicates_listing_and_updates(): void
    {
        [$tenant] = $this->clinic();
        $this->postJson(self::ROOT, [])->assertUnprocessable()->assertJsonValidationErrors(['first_name', 'last_name', 'gender']);
        $this->postJson(self::ROOT, $this->data(['date_of_birth' => now()->addDay()->toDateString(), 'gender' => 'invalid', 'email' => 'bad', 'tenant_id' => 123, 'patient_number' => 'CUSTOM']))->assertUnprocessable();
        $id = $this->create(['notes' => 'Sensitive intake note', 'allergy' => 'Penicillin', 'condition' => 'Asthma']);
        $this->getJson(self::ROOT.'/'.$id)->assertOk()->assertJsonPath('data.patient_number', 'ALPHA-000001')->assertJsonPath('data.notes', 'Sensitive intake note');
        $this->postJson(self::ROOT, $this->data())->assertStatus(409)->assertJsonPath('duplicates.0.id', $id);
        $second = $this->create(['confirm_duplicate' => true]);
        $this->getJson(self::ROOT.'/'.$second)->assertJsonPath('data.patient_number', 'ALPHA-000002');
        $this->getJson(self::ROOT.'?search=Amina%20Noor%20Hassan&gender=female&per_page=25')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('stats.total', 2)->assertJsonMissingPath('data.0.notes')->assertJsonMissingPath('data.0.email');
        $this->getJson(self::ROOT.'?search=ALPHA-000001')->assertJsonCount(1, 'data');
        $this->getJson(self::ROOT.'?gender=male')->assertJsonCount(0, 'data');
        $this->getJson(self::ROOT.'?to='.now()->subDay()->toDateString())->assertOk()->assertJsonCount(0, 'data');
        $this->putJson(self::ROOT.'/'.$id, $this->data(['city' => 'Hargeisa']))->assertOk();
        $this->getJson(self::ROOT.'/'.$id)->assertJsonPath('data.city', 'Hargeisa');
        $this->postJson(self::ROOT.'/'.$id.'/archive')->assertOk()->assertJsonPath('data.status', 'archived');
        $this->getJson(self::ROOT)->assertJsonCount(1, 'data')->assertJsonPath('stats.archived', 1);
        $this->putJson(self::ROOT.'/'.$id, $this->data())->assertUnprocessable();
        $this->postJson(self::ROOT.'/'.$id.'/restore')->assertOk()->assertJsonPath('data.status', 'active');
        $this->assertDatabaseCount('patients', 2);
        $this->getJson('/api/v1/clinic/dashboard')->assertOk()->assertJsonPath('data.stats.total_patients', 2)->assertJsonCount(2, 'data.recent_patients');
        $this->getJson(self::ROOT.'/'.$id.'/activity')->assertOk()->assertJsonPath('data.data.0.action', 'patient.restored');
        $logs = DB::table('platform_audit_logs')->where('tenant_id', $tenant->id)->get()->toJson();
        $this->assertStringNotContainsString('Sensitive intake note', $logs);
        $this->assertStringNotContainsString('Penicillin', $logs);
    }

    public function test_medical_history_permissions_and_child_ownership(): void
    {
        [$tenant, $owner] = $this->clinic();
        $id = $this->create(['notes' => 'Restricted', 'allergy' => 'Penicillin']);
        $other = $this->create(['first_name' => 'Different', 'phone' => null, 'email' => null]);
        $allergy = $this->postJson(self::ROOT.'/'.$id.'/allergies', ['allergen' => 'Peanuts', 'severity' => 'severe', 'status' => 'active'])->assertCreated()->json('data.id');
        $this->putJson(self::ROOT.'/'.$other.'/allergies/'.$allergy, ['allergen' => 'Peanuts', 'status' => 'inactive'])->assertNotFound();
        $this->putJson(self::ROOT.'/'.$id.'/allergies/'.$allergy, ['allergen' => 'Peanuts', 'status' => 'inactive'])->assertOk();
        $condition = $this->postJson(self::ROOT.'/'.$id.'/conditions', ['condition_name' => 'Asthma', 'status' => 'active'])->assertCreated()->json('data.id');
        $this->putJson(self::ROOT.'/'.$id.'/conditions/'.$condition, ['condition_name' => 'Asthma', 'status' => 'resolved'])->assertOk();
        DB::table('tenant_memberships')->where('tenant_id', $tenant->id)->where('user_id', $owner->id)->update(['role' => 'receptionist']);
        $this->getJson(self::ROOT.'/'.$id)->assertOk()->assertJsonMissingPath('data.notes')->assertJsonMissingPath('data.allergies');
        $this->getJson(self::ROOT.'/'.$id.'/allergies')->assertForbidden();
        $this->getJson(self::ROOT.'/'.$id.'/conditions')->assertForbidden();
        $this->getJson(self::ROOT.'/'.$id.'/documents')->assertForbidden();
        $this->putJson(self::ROOT.'/'.$id, $this->data(['notes' => 'Overwrite']))->assertForbidden();
        $this->putJson(self::ROOT.'/'.$id, $this->data(['notes' => null]))->assertOk();
        $this->assertDatabaseHas('patients', ['id' => $id, 'notes' => 'Restricted']);
        $this->getJson(self::ROOT.'/'.$id.'/activity')->assertOk()->assertJsonMissing(['action' => 'patient.allergy.created']);
    }

    public function test_patient_and_document_tenant_isolation_and_private_uploads(): void
    {
        Storage::fake('patient_private');
        [$a, $owner] = $this->clinic();
        $id = $this->create();
        $doc = $this->postJson(self::ROOT.'/'.$id.'/documents', ['title' => 'Referral', 'document_type' => 'referral', 'file' => UploadedFile::fake()->createWithContent('referral.pdf', "%PDF-1.4\nTest")])->assertCreated()->assertJsonMissingPath('data.path')->json('data.id');
        $this->getJson(self::ROOT.'/'.$id.'/documents/'.$doc.'/download')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->postJson(self::ROOT.'/'.$id.'/documents', ['title' => 'Bad file', 'document_type' => 'other', 'file' => UploadedFile::fake()->create('bad.exe', 10)])->assertUnprocessable();
        [$b] = $this->clinic('beta');
        $this->getJson(self::ROOT.'/'.$id)->assertNotFound();
        $this->putJson(self::ROOT.'/'.$id, $this->data())->assertNotFound();
        $this->postJson(self::ROOT.'/'.$id.'/archive')->assertNotFound();
        $this->getJson(self::ROOT.'/'.$id.'/documents/'.$doc.'/download')->assertNotFound();
        $this->getJson(self::ROOT.'?search=Amina')->assertJsonCount(0, 'data');
        $foreignId = $this->create(); // Identical demographics must not warn across tenants.
        $this->getJson(self::ROOT.'/'.$foreignId)->assertJsonPath('data.patient_number', 'BETA-000001');
        $this->flushSession(); $this->app['auth']->forgetGuards();
        $this->actingAs($owner, 'web')->postJson('/api/v1/session/clinic', ['clinic_id' => $a->id])->assertOk();
        $this->postJson(self::ROOT.'/'.$id.'/documents/'.$doc.'/archive')->assertNoContent();
        $this->getJson(self::ROOT.'/'.$id.'/documents/'.$doc.'/download')->assertNotFound();
        $this->assertDatabaseCount('patient_documents', 1);
        $this->postJson('/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->getJson(self::ROOT.'/'.$id.'/documents/'.$doc.'/download')->assertUnauthorized();
    }

    public function test_limits_features_and_branch_context_are_enforced(): void
    {
        [$tenant, $owner, $plan] = $this->clinic();
        $plan->update(['patient_limit' => 1]); $id = $this->create();
        $this->postJson(self::ROOT.'/'.$id.'/archive')->assertOk();
        $this->postJson(self::ROOT, $this->data(['confirm_duplicate' => true]))->assertUnprocessable()->assertJsonValidationErrors('plan');
        $plan->update(['storage_limit_gb' => 0]);
        $this->postJson(self::ROOT.'/'.$id.'/restore')->assertOk();
        $this->postJson(self::ROOT.'/'.$id.'/documents', ['title' => 'Referral', 'document_type' => 'referral', 'file' => UploadedFile::fake()->createWithContent('r.pdf', "%PDF-1.4\nTest")])->assertUnprocessable()->assertJsonValidationErrors('file');
        $branch = DB::table('branches')->insertGetId(['tenant_id' => $tenant->id, 'name' => 'Second']);
        $this->postJson('/api/v1/clinic/branch', ['branch_id' => $branch])->assertOk();
        $this->getJson(self::ROOT.'/'.$id)->assertOk(); // Tenant-wide identity is shared across authorized branches.
        $this->withHeader('X-Branch-Context', '999999')->getJson(self::ROOT)->assertStatus(409);
        $this->flushHeaders(); $this->withHeader('Origin', 'http://localhost');
        DB::table('tenant_memberships')->where('user_id', $owner->id)->update(['all_branches' => false]);
        $this->getJson(self::ROOT)->assertForbidden();
        DB::table('tenant_memberships')->where('user_id', $owner->id)->update(['all_branches' => true]);
        $plan->update(['features' => ['patient_management' => false]]);
        $this->getJson(self::ROOT)->assertForbidden();
        $plan->update(['features' => ['patient_management' => true]]);
        DB::table('subscriptions')->where('tenant_id', $tenant->id)->update(['status' => 'suspended']);
        $this->getJson(self::ROOT)->assertForbidden();
    }

    public function test_server_pagination_and_sorting(): void
    {
        $this->clinic();
        for ($i = 0; $i < 26; $i++) $this->create(['first_name' => 'Patient '.str_pad($i, 2, '0', STR_PAD_LEFT), 'phone' => null, 'email' => null]);
        $this->getJson(self::ROOT.'?per_page=25&sort=name&direction=asc')->assertOk()->assertJsonCount(25, 'data')->assertJsonPath('meta.total', 26)->assertJsonPath('data.0.first_name', 'Patient 00');
        $this->getJson(self::ROOT.'?per_page=25&page=2&sort=name&direction=asc')->assertJsonCount(1, 'data')->assertJsonPath('data.0.first_name', 'Patient 25');
        $this->getJson(self::ROOT.'?per_page=500')->assertUnprocessable();
    }

    public function test_gender_and_age_entry_are_validated_without_inventing_a_birthday(): void
    {
        $this->clinic();
        foreach (['other', 'unknown'] as $gender) $this->postJson(self::ROOT, $this->data(['gender' => $gender]))->assertUnprocessable()->assertJsonValidationErrors('gender');
        $this->postJson(self::ROOT, $this->data(['reported_age' => 32]))->assertUnprocessable();
        $this->postJson(self::ROOT, $this->data(['date_of_birth' => null, 'reported_age' => -1]))->assertUnprocessable();
        $id = $this->create(['date_of_birth' => null, 'reported_age' => 32]);
        $this->getJson(self::ROOT.'/'.$id)->assertOk()->assertJsonPath('data.date_of_birth', null)->assertJsonPath('data.age', 32)->assertJsonPath('data.age_is_estimated', true);
        $this->putJson(self::ROOT.'/'.$id, $this->data(['reported_age' => null]))->assertOk();
        $this->getJson(self::ROOT.'/'.$id)->assertJsonPath('data.age_is_estimated', false)->assertJsonPath('data.date_of_birth', '1992-04-15');
    }
}

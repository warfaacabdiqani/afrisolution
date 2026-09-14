<?php
namespace Tests\Feature;
use App\Models\{Plan,User};
use App\Services\{PlatformService,ClinicSettingsService,SystemSettingsService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
class ClinicSettingsTest extends TestCase {
    use RefreshDatabase;
    private const ROOT='/api/v1/clinic/settings';
    private function select(User $user, int $tenant): void
    {
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->withHeader('Origin', 'http://localhost')->actingAs($user, 'web')->postJson('/api/v1/session/clinic', ['clinic_id' => $tenant])->assertOk();
    }

    private function clinic(string $slug = 'alpha'): array
    {
        $actor = User::factory()->create();
        $plan = Plan::create(['name' => 'Scheduling', 'branch_limit' => 3, 'member_limit' => 10, 'doctor_limit' => 10, 'appointment_limit' => 100, 'trial_days' => 14, 'features' => ['billing'=>true, 'email_notifications'=>true, 'sms_notifications'=>true, 'whatsapp_notifications'=>true, 'prescriptions' => true, 'pharmacy' => true, 'appointments' => true, 'patient_management' => true, 'clinicians' => true, 'multi_branch' => true]]);
        $tenant = app(PlatformService::class)->createTenant(['name' => $slug, 'slug' => $slug, 'timezone' => 'Africa/Nairobi', 'plan_id' => $plan->id, 'owner_name' => 'Owner', 'owner_email' => $slug.'@example.test', 'owner_password' => 'SecurePass12345'], $actor->id);
        $owner = User::where('email', $slug.'@example.test')->firstOrFail();
        $this->select($owner, $tenant->id);
        $branch = DB::table('branches')->where('tenant_id', $tenant->id)->value('id');
        $specialty = $this->postJson('/api/v1/clinic/specialties', ['name' => 'General Practice'])->assertCreated()->json('data.id');
        $doctor = $this->postJson('/api/v1/clinic/doctors', ['availability_status' => 'available', 'first_name' => 'Ahmed', 'last_name' => 'Hassan', 'primary_branch_id' => $branch, 'specialty_ids' => [$specialty]])->assertCreated()->json('data.id');
        $this->putJson('/api/v1/clinic/doctors/'.$doctor.'/schedule', ['branch_id' => $branch, 'days' => array_map(fn ($day) => ['day_of_week' => $day, 'is_available' => true, 'start_time' => '08:00', 'end_time' => '16:00', 'break_start' => '12:00', 'break_end' => '13:00'], range(1, 7))])->assertNoContent();
        $patient = $this->postJson('/api/v1/clinic/patients', ['first_name' => 'Amina', 'last_name' => 'Yusuf', 'gender' => 'female'])->assertCreated()->json('data.id');
        $type = $this->postJson('/api/v1/clinic/appointment-types', ['name' => 'General Consultation', 'default_duration' => 30])->assertCreated()->json('data.id');

        return compact('tenant', 'owner', 'plan', 'branch', 'doctor', 'patient', 'type', 'specialty');
    }



    private function values(string $section,array $changes=[]): array {
        $values=$this->getJson(self::ROOT)->assertOk()->json('data.sections.'.$section.'.values');
        if($section==='general') unset($values['code'],$values['status']);
        return array_replace($values,$changes);
    }
    public function test_general_persists_without_affecting_platform_or_identifiers(): void {
        $c=$this->clinic();
        $this->putJson(self::ROOT.'/general',$this->values('general',['name'=>'Updated clinic','phone'=>'+252 611 123 456','currency'=>'KES']))->assertOk()->assertJsonPath('data.name','Updated clinic');
        $this->getJson(self::ROOT)->assertJsonPath('data.summary.clinic.name','Updated clinic')->assertJsonPath('data.sections.general.values.phone','+252 611 123 456');
        $this->assertDatabaseHas('tenants',['id'=>$c['tenant']->id,'slug'=>$c['tenant']->slug]);
        $this->assertDatabaseCount('system_settings',0);
        foreach(['code'=>'hijack','status'=>'inactive','tenant_id'=>999,'smtp_password'=>'secret'] as $k=>$v) $this->putJson(self::ROOT.'/general',$this->values('general',[$k=>$v]))->assertUnprocessable();
        $audit=DB::table('platform_audit_logs')->where('action','clinic.settings.general.updated')->first();
        $this->assertStringContainsString('name',$audit->metadata);$this->assertStringNotContainsString('Updated clinic',$audit->metadata);
    }
    public function test_patient_numbers_and_required_fields_only_affect_future_records(): void {
        $c=$this->clinic();$old=DB::table('patients')->where('id',$c['patient'])->value('patient_number');
        $this->putJson(self::ROOT.'/patients',$this->values('patients',['number_prefix'=>'WAR-PAT-','number_length'=>5,'require_phone'=>true]))->assertOk();
        $this->postJson('/api/v1/clinic/patients',['first_name'=>'New','last_name'=>'Patient','gender'=>'female'])->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->postJson('/api/v1/clinic/patients',['first_name'=>'New','last_name'=>'Patient','gender'=>'female','phone'=>'+252611111222'])->assertCreated()->assertJsonPath('data.patient_number','WAR-PAT-00002');
        $this->assertDatabaseHas('patients',['id'=>$c['patient'],'patient_number'=>$old]);
        $this->putJson(self::ROOT.'/patients',$this->values('patients',['require_gender'=>false]))->assertUnprocessable();
        $this->putJson(self::ROOT.'/patients',$this->values('patients',['number_prefix'=>'../../bad']))->assertUnprocessable();
    }
    public function test_appointment_defaults_and_scheduling_policy(): void {
        $c=$this->clinic();
        $this->putJson(self::ROOT.'/appointments',$this->values('appointments',['default_duration'=>45,'slot_interval'=>20,'allow_walk_in'=>false,'allow_same_day'=>false]))->assertOk();
        $this->getJson('/api/v1/clinic/appointments/options')->assertJsonPath('data.defaults.default_duration',45)->assertJsonPath('data.defaults.slot_interval',20);
        $data=['branch_id'=>$c['branch'],'patient_id'=>$c['patient'],'doctor_id'=>$c['doctor'],'date'=>now('Africa/Nairobi')->toDateString(),'start_time'=>'10:00','duration'=>30,'source'=>'walk_in'];
        $this->postJson('/api/v1/clinic/appointments',$data)->assertUnprocessable();
        $this->putJson(self::ROOT.'/appointments',$this->values('appointments',['allow_double_booking'=>true]))->assertUnprocessable();
        $this->putJson(self::ROOT.'/appointments',$this->values('appointments',['working_start'=>'18:00','working_end'=>'08:00']))->assertUnprocessable();
    }
    public function test_branch_management_limits_and_main_branch_protection(): void {
        $c=$this->clinic();$data=['name'=>'East branch','code'=>'EAST','phone'=>'12345','timezone'=>'Africa/Nairobi','status'=>'active'];
        $id=$this->postJson(self::ROOT.'/branches',$data)->assertCreated()->json('data.id');
        $this->putJson(self::ROOT.'/branches/'.$id,array_replace($data,['phone'=>'67890']))->assertOk();
        $this->getJson(self::ROOT.'/branches')->assertJsonCount(2,'data');
        $this->putJson(self::ROOT.'/branches/'.$c['branch'],array_replace($data,['name'=>'Main branch','code'=>'MAIN','status'=>'inactive']))->assertUnprocessable();
        $this->postJson(self::ROOT.'/branches',array_replace($data,['name'=>'West branch','code'=>'WEST']))->assertCreated();
        $this->postJson(self::ROOT.'/branches',array_replace($data,['name'=>'Fourth','code'=>'FOUR']))->assertUnprocessable();
    }
    public function test_tenant_isolation_cache_assets_branches_and_settings(): void {
        $a=$this->clinic('alpha');$this->putJson(self::ROOT.'/general',$this->values('general',['phone'=>'ALPHA-ONLY']))->assertOk();
        $b=$this->clinic('beta');$this->getJson(self::ROOT)->assertJsonPath('data.sections.general.values.phone','');
        $this->putJson(self::ROOT.'/branches/'.$a['branch'],['name'=>'Other','code'=>'OTHER','timezone'=>'Africa/Nairobi','status'=>'active'])->assertNotFound();
        $this->putJson(self::ROOT.'/pharmacy',$this->values('pharmacy',['default_branch_id'=>$a['branch']]))->assertUnprocessable();
        $this->getJson(self::ROOT.'/branding/logo?tenant_id='.$a['tenant']->id)->assertNotFound();
        $this->select($a['owner'],$a['tenant']->id);$this->getJson(self::ROOT)->assertJsonPath('data.sections.general.values.phone','ALPHA-ONLY');
    }
    public function test_permissions_and_plan_features(): void {
        $c=$this->clinic();$data=$this->values('general');
        DB::table('tenant_memberships')->where('user_id',$c['owner']->id)->update(['permissions'=>json_encode(['clinic_settings.view'])]);
        $this->getJson(self::ROOT)->assertOk()->assertJsonPath('data.sections.general.can_update',false);
        $this->putJson(self::ROOT.'/general',$data)->assertForbidden();
        $this->postJson(self::ROOT.'/branches',[])->assertForbidden();
        $this->postJson(self::ROOT.'/branding',[])->assertForbidden();
        DB::table('tenant_memberships')->where('user_id',$c['owner']->id)->update(['permissions'=>json_encode(['clinic_settings.view','clinic_settings.general.update'])]);
        $this->putJson(self::ROOT.'/general',$data)->assertOk();$this->putJson(self::ROOT.'/security',[])->assertForbidden();
        $c['plan']->update(['features'=>['patient_management'=>true]]);
        $this->getJson(self::ROOT)->assertJsonMissingPath('data.sections.pharmacy')->assertJsonMissingPath('data.sections.billing')->assertJsonMissingPath('data.sections.notifications');
        $this->putJson(self::ROOT.'/pharmacy',[])->assertForbidden();$this->putJson(self::ROOT.'/billing',[])->assertForbidden();
    }
    public function test_branding_validation_and_private_document_previews(): void {
        Storage::fake('patient_private');$c=$this->clinic();
        $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aDXsAAAAASUVORK5CYII=');
        $this->postJson(self::ROOT.'/branding',['asset'=>'logo','file'=>UploadedFile::fake()->createWithContent('logo.png',$png)])->assertOk();
        $this->get(self::ROOT.'/branding/logo')->assertOk()->assertHeader('Content-Type','image/png');
        $this->getJson(self::ROOT)->assertJsonPath('data.sections.branding.values.logo',true)->assertJsonMissingPath('data.sections.branding.values.logo.path');
        $this->postJson(self::ROOT.'/branding',['asset'=>'logo','file'=>UploadedFile::fake()->createWithContent('logo.svg','<svg></svg>')])->assertUnprocessable();
        $this->putJson(self::ROOT.'/documents',$this->values('documents',['prescription_footer'=>'Clinic-specific footer','show_license'=>false]))->assertOk();
        $this->get(self::ROOT.'/preview/prescription')->assertOk()->assertSee('Clinic-specific footer')->assertSee('data:image/png;base64',false)->assertDontSee('Doctor license:');
        $this->clinic('beta');$this->get(self::ROOT.'/branding/logo')->assertNotFound();
    }
    public function test_security_cannot_weaken_platform_and_enforces_inactivity(): void {
        $c=$this->clinic();app(SystemSettingsService::class)->setSection('security',['session_lifetime'=>60,'minimum_password_length'=>14]);
        $this->putJson(self::ROOT.'/security',$this->values('security',['session_timeout'=>120]))->assertUnprocessable();
        $this->putJson(self::ROOT.'/security',$this->values('security',['minimum_password_length'=>12]))->assertUnprocessable();
        $this->putJson(self::ROOT.'/security',$this->values('security',['session_timeout'=>15,'minimum_password_length'=>14]))->assertOk();
        $this->travel(16)->minutes();$this->getJson(self::ROOT)->assertUnauthorized();
    }
    public function test_subscription_usage_and_billing_notification_preferences(): void {
        $c=$this->clinic();
        $this->getJson(self::ROOT.'/subscription')->assertOk()->assertJsonPath('data.usage.branches',1)->assertJsonPath('data.usage.patients',1)->assertJsonPath('data.usage.doctors',1);
        $this->putJson(self::ROOT.'/billing',$this->values('billing',['invoice_prefix'=>'WAR-INV-','receipt_prefix'=>'WAR-RCT-','tax_rate'=>5,'payment_methods'=>['cash','mobile_money']]))->assertOk();
        $this->get(self::ROOT.'/preview/invoice')->assertOk()->assertSee('WAR-INV-000001');
        $this->putJson(self::ROOT.'/notifications',$this->values('notifications',['email_enabled'=>true]))->assertOk();
        $this->putJson(self::ROOT.'/notifications',$this->values('notifications',['sms_secret'=>'should-not-be-accepted']))->assertUnprocessable();
        $this->assertStringNotContainsString('should-not-be-accepted',DB::table('platform_audit_logs')->get()->toJson());
    }
    public function test_branch_restricted_settings_manager_cannot_edit_other_branch(): void {
        $c=$this->clinic();$id=$this->postJson(self::ROOT.'/branches',['name'=>'Other','timezone'=>'Africa/Nairobi','status'=>'active'])->json('data.id');
        $member=DB::table('tenant_memberships')->where('user_id',$c['owner']->id)->first();
        DB::table('tenant_memberships')->where('id',$member->id)->update(['all_branches'=>false]);
        DB::table('branch_memberships')->insert(['tenant_id'=>$c['tenant']->id,'membership_id'=>$member->id,'branch_id'=>$c['branch']]);
        $this->getJson(self::ROOT.'/branches')->assertJsonCount(1,'data');
        $this->putJson(self::ROOT.'/branches/'.$id,['name'=>'Other','timezone'=>'Africa/Nairobi','status'=>'active'])->assertNotFound();
    }

    public function test_staff_access_policy_preserves_settings_manager_recovery(): void {
        $c=$this->clinic();
        $this->putJson(self::ROOT.'/security',$this->values('security',['allow_staff_login'=>false]))->assertOk();
        $this->getJson(self::ROOT)->assertOk();
        $staff=User::factory()->create();
        DB::table('tenant_memberships')->insert(['tenant_id'=>$c['tenant']->id,'user_id'=>$staff->id,'role'=>'staff','status'=>'active','all_branches'=>true,'permissions'=>json_encode(['patients.view']),'created_at'=>now(),'updated_at'=>now()]);
        $this->select($staff,$c['tenant']->id);
        $this->getJson('/api/v1/clinic/patients')->assertForbidden();
        $this->getJson('/api/v1/clinic/context')->assertJsonPath('data.operational',false);
        $this->select($c['owner'],$c['tenant']->id);
        $this->putJson(self::ROOT.'/security',$this->values('security',['allow_staff_login'=>true]))->assertOk();
    }
    public function test_document_preferences_and_validity_feed_real_prescription_printing(): void {
        $c=$this->clinic();
        $this->putJson(self::ROOT.'/clinical',$this->values('clinical',['prescription_validity_days'=>7]))->assertOk();
        $this->putJson(self::ROOT.'/general',$this->values('general',['address'=>'Clinic address for print','phone'=>'12345678']))->assertOk();
        $this->putJson(self::ROOT.'/documents',$this->values('documents',['prescription_header'=>'Patient Medication Order','prescription_footer'=>'Our clinic footer','show_phone'=>false,'show_license'=>false]))->assertOk();
        $date=now('Africa/Nairobi')->toDateString();
        $p=$this->postJson('/api/v1/clinic/prescriptions',['branch_id'=>$c['branch'],'patient_id'=>$c['patient'],'doctor_id'=>$c['doctor'],'prescription_date'=>$date,'status'=>'active','internal_notes'=>'Private prescription notes','items'=>[['medication_name'=>'Medicine','dose'=>'1 tablet','route'=>'oral','frequency'=>'daily','duration'=>'7 days']]])->assertCreated()->json('data');
        $this->assertSame(now('Africa/Nairobi')->addDays(7)->toDateString(),$p['expires_on']);
        $this->get('/api/v1/clinic/prescriptions/'.$p['id'].'/print')->assertOk()->assertSee('Our clinic footer')->assertSee('Patient Medication Order')->assertSee('Clinic address for print')->assertDontSee('12345678')->assertDontSee('Private prescription notes');
        $this->putJson(self::ROOT.'/clinical',$this->values('clinical',['prescription_validity_days'=>14]))->assertOk();
        $this->assertDatabaseHas('prescriptions',['id'=>$p['id'],'expires_on'=>$p['expires_on']]);
    }
}

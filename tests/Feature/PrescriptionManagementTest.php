<?php
namespace Tests\Feature;
use App\Models\{Plan,User};
use App\Services\PlatformService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class PrescriptionManagementTest extends TestCase {
    use RefreshDatabase;
    private const ROOT='/api/v1/clinic/prescriptions';
    private function select(User $user, int $tenant): void
    {
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->withHeader('Origin', 'http://localhost')->actingAs($user, 'web')->postJson('/api/v1/session/clinic', ['clinic_id' => $tenant])->assertOk();
    }

    private function clinic(string $slug = 'alpha'): array
    {
        $actor = User::factory()->create();
        $plan = Plan::create(['name' => 'Scheduling', 'branch_limit' => 3, 'member_limit' => 10, 'doctor_limit' => 10, 'appointment_limit' => 100, 'trial_days' => 14, 'features' => ['prescriptions' => true, 'pharmacy' => true, 'appointments' => true, 'patient_management' => true, 'clinicians' => true, 'multi_branch' => true]]);
        $tenant = app(PlatformService::class)->createTenant(['name' => $slug, 'slug' => $slug, 'timezone' => 'Africa/Nairobi', 'plan_id' => $plan->id, 'owner_name' => 'Owner', 'owner_email' => $slug.'@example.test', 'owner_password' => 'SecurePass12345'], $actor->id);
        // Preserve legacy tenant identifiers in patient-number search fixtures.
        $tenant->update(['slug' => $slug]);
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


    private function data(array $c,array $extra=[]): array {
        return array_merge(['branch_id'=>$c['branch'],'patient_id'=>$c['patient'],'doctor_id'=>$c['doctor'],'prescription_date'=>now('Africa/Nairobi')->toDateString(),'status'=>'active','items'=>[
            ['medication_name'=>'Amoxicillin','strength'=>'500 mg','dose'=>'1 capsule','route'=>'oral','frequency'=>'tid','duration'=>'7 days','quantity'=>21,'instructions'=>'After meals'],
            ['medication_name'=>'Paracetamol','dose'=>'1 tablet','route'=>'oral','frequency'=>'prn','duration'=>'3 days','quantity'=>6],
        ]],$extra);
    }
    public function test_creation_multiple_items_draft_update_numbers_audit_and_print(): void {
        $c=$this->clinic();
        $id=$this->postJson(self::ROOT,$this->data($c,['internal_notes'=>'Private clinical note']))->assertCreated()->assertJsonPath('data.prescription_number','RX-000001')->assertJsonCount(2,'data.items')->json('data.id');
        $this->postJson(self::ROOT,$this->data($c,['status'=>'draft']))->assertCreated()->assertJsonPath('data.status','draft')->assertJsonPath('data.prescription_number','RX-000002');
        $this->putJson(self::ROOT.'/'.$id,$this->data($c,['diagnosis'=>'Updated indication','internal_notes'=>'Private clinical note']))->assertOk()->assertJsonPath('data.diagnosis','Updated indication');
        $this->get(self::ROOT.'/'.$id.'/print')->assertOk()->assertSee('Amoxicillin')->assertDontSee('Private clinical note')->assertDontSee('internal_notes');
        $this->getJson(self::ROOT.'/'.$id.'/activity')->assertOk()->assertJsonPath('data.data.0.action','prescription.printed');
        $logs=DB::table('platform_audit_logs')->where('action','like','prescription.%')->get()->toJson();
        $this->assertStringNotContainsString('Private clinical note',$logs);
        $this->assertStringNotContainsString('Updated indication',$logs);
        $this->deleteJson(self::ROOT.'/'.$id)->assertMethodNotAllowed();
    }
    public function test_validation_and_nested_field_injection(): void {
        $c=$this->clinic();
        foreach([[],['patient_id'=>999999],['doctor_id'=>999999],['branch_id'=>999999],['consultation_id'=>1],['tenant_id'=>1],['status'=>'dispensed'],['items'=>[]],['prescription_date'=>'2026-02-30']] as $invalid) {
            $this->postJson(self::ROOT,$invalid===[]?[]:$this->data($c,$invalid))->assertUnprocessable();
        }
        foreach(['tenant_id'=>999,'status'=>'dispensed','dispensed_quantity'=>21,'prescription_id'=>999] as $field=>$value) {
            $data=$this->data($c); $data['items'][0][$field]=$value;
            $this->postJson(self::ROOT,$data)->assertUnprocessable();
        }
        $data=$this->data($c);$data['items'][0]['frequency']='custom';
        $this->postJson(self::ROOT,$data)->assertUnprocessable();
        $data['items'][0]['custom_frequency']='Every other day';$this->postJson(self::ROOT,$data)->assertCreated();
    }
    public function test_search_filters_pagination_and_statistics(): void {
        $c=$this->clinic();$this->postJson(self::ROOT,$this->data($c))->assertCreated();
        $this->postJson(self::ROOT,$this->data($c,['status'=>'draft','prescription_date'=>'2020-01-01']))->assertCreated();
        foreach(['Amina Yusuf','ALPHA-000001','Amoxicillin','Ahmed Hassan','RX-000001'] as $term) $this->getJson(self::ROOT.'?search='.urlencode($term))->assertOk()->assertJsonPath('meta.total',$term==='RX-000001'?1:2);
        foreach(['status=draft','from=2019-01-01&to=2020-12-31','to=2020-12-31'] as $filter) $this->getJson(self::ROOT.'?'.$filter)->assertJsonPath('meta.total',1);
        $this->getJson(self::ROOT.'?medication=missing')->assertJsonPath('meta.total',0);
        $this->getJson(self::ROOT.'?patient_id='.$c['patient'].'&doctor_id='.$c['doctor'].'&branch_id='.$c['branch'].'&per_page=1&page=2')->assertJsonPath('meta.total',2)->assertJsonCount(1,'data')->assertJsonPath('meta.current_page',2);
        $this->getJson(self::ROOT.'/stats')->assertOk()->assertJsonPath('data.total',2)->assertJsonPath('data.today',1)->assertJsonPath('data.active',1);
    }
    public function test_cancel_requires_reason_and_retains_record(): void {
        $c=$this->clinic();$id=$this->postJson(self::ROOT,$this->data($c))->json('data.id');
        $this->postJson(self::ROOT.'/'.$id.'/cancel',[])->assertUnprocessable();
        $this->postJson(self::ROOT.'/'.$id.'/cancel',['reason'=>'Replaced by clinician'])->assertOk()->assertJsonPath('data.status','cancelled')->assertJsonPath('data.cancellation_reason','Replaced by clinician');
        $this->putJson(self::ROOT.'/'.$id,$this->data($c))->assertUnprocessable();
        $this->assertDatabaseHas('prescriptions',['id'=>$id,'cancelled_by'=>$c['owner']->id]);
        $this->getJson(self::ROOT.'/'.$id.'/activity')->assertJsonPath('data.data.0.action','prescription.cancelled');
    }
    public function test_pharmacy_partial_full_dispensing_and_edit_restrictions(): void {
        $c=$this->clinic();$p=$this->postJson(self::ROOT,$this->data($c))->json('data');
        $this->postJson(self::ROOT.'/'.$p['id'].'/send-to-pharmacy')->assertOk()->assertJsonPath('data.status','pending');
        $this->getJson(self::ROOT.'/stats')->assertJsonPath('data.pending',1);
        $item=['id'=>$p['items'][0]['id'],'dispensed_quantity'=>10];
        $this->postJson(self::ROOT.'/'.$p['id'].'/dispense',['items'=>[$item]])->assertOk()->assertJsonPath('data.status','partially_dispensed');
        $this->putJson(self::ROOT.'/'.$p['id'],$this->data($c))->assertUnprocessable();
        $this->postJson(self::ROOT.'/'.$p['id'].'/dispense',['items'=>[['id'=>$item['id'],'dispensed_quantity'=>22]]])->assertUnprocessable();
        $this->postJson(self::ROOT.'/'.$p['id'].'/dispense',['items'=>[['id'=>$item['id'],'dispensed_quantity'=>21],['id'=>$p['items'][1]['id'],'dispensed_quantity'=>6]]])->assertOk()->assertJsonPath('data.status','dispensed');
        $this->postJson(self::ROOT.'/'.$p['id'].'/cancel',['reason'=>'Closed'])->assertUnprocessable();
    }
    public function test_tenant_isolation_for_records_and_references(): void {
        $a=$this->clinic('alpha');$id=$this->postJson(self::ROOT,$this->data($a))->json('data.id');
        $med=$this->postJson('/api/v1/clinic/medications',['name'=>'Tenant A medicine'])->assertCreated()->json('data.id');
        $b=$this->clinic('beta');
        $this->getJson(self::ROOT.'/'.$id)->assertNotFound();
        $this->putJson(self::ROOT.'/'.$id,$this->data($b))->assertNotFound();
        $this->postJson(self::ROOT.'/'.$id.'/cancel',['reason'=>'No'])->assertNotFound();
        foreach(['patient_id'=>$a['patient'],'doctor_id'=>$a['doctor'],'branch_id'=>$a['branch'],'consultation_id'=>123] as $field=>$value) $this->postJson(self::ROOT,$this->data($b,[$field=>$value]))->assertUnprocessable();
        $data=$this->data($b);$data['items'][0]['medication_id']=$med;$this->postJson(self::ROOT,$data)->assertUnprocessable();
        $this->getJson('/api/v1/clinic/medications/search?search=Tenant')->assertJsonCount(0,'data');
        $this->getJson(self::ROOT)->assertJsonCount(0,'data');
    }
    public function test_permissions_and_plan_enforced_for_direct_requests(): void {
        $c=$this->clinic();$id=$this->postJson(self::ROOT,$this->data($c))->json('data.id');
        DB::table('tenant_memberships')->where('tenant_id',$c['tenant']->id)->where('user_id',$c['owner']->id)->update(['permissions'=>json_encode(['prescriptions.view','prescriptions.view_all_doctors'])]);
        $this->getJson(self::ROOT)->assertOk();
        $this->postJson(self::ROOT,$this->data($c))->assertForbidden();
        $this->putJson(self::ROOT.'/'.$id,$this->data($c))->assertForbidden();
        $this->postJson(self::ROOT.'/'.$id.'/cancel',['reason'=>'No'])->assertForbidden();
        $this->getJson(self::ROOT.'/'.$id.'/print')->assertForbidden();
        $c['plan']->update(['features'=>['prescriptions'=>false,'multi_branch'=>true]]);
        $this->getJson(self::ROOT)->assertForbidden();$this->getJson(self::ROOT.'/stats')->assertForbidden();$this->getJson(self::ROOT.'/'.$id)->assertForbidden();
    }
    public function test_branch_and_prescriber_isolation(): void {
        $c=$this->clinic();$id=$this->postJson(self::ROOT,$this->data($c))->json('data.id');
        $branch=DB::table('branches')->insertGetId(['tenant_id'=>$c['tenant']->id,'name'=>'Restricted','created_at'=>now(),'updated_at'=>now()]);
        $member=DB::table('tenant_memberships')->where('tenant_id',$c['tenant']->id)->where('user_id',$c['owner']->id)->value('id');
        DB::table('tenant_memberships')->where('id',$member)->update(['all_branches'=>false]);
        DB::table('branch_memberships')->where('membership_id',$member)->delete();
        DB::table('branch_memberships')->insert(['tenant_id'=>$c['tenant']->id,'membership_id'=>$member,'branch_id'=>$branch]);
        $this->getJson(self::ROOT.'/'.$id)->assertNotFound();
        $this->getJson(self::ROOT)->assertJsonCount(0,'data');
        $this->postJson(self::ROOT,$this->data($c))->assertUnprocessable();
        $this->getJson(self::ROOT.'/patients?id='.$c['patient'])->assertJsonCount(0,'data');
        DB::table('tenant_memberships')->where('id',$member)->update(['all_branches'=>true,'permissions'=>json_encode(['prescriptions.view','prescriptions.create'])]);
        $this->getJson(self::ROOT.'/'.$id)->assertNotFound();
        $this->postJson(self::ROOT,$this->data($c))->assertUnprocessable();
    }
    public function test_appointment_linkage_matches_patient_doctor_branch(): void {
        $c=$this->clinic();
        $id=DB::table('appointments')->insertGetId(['tenant_id'=>$c['tenant']->id,'branch_id'=>$c['branch'],'patient_id'=>$c['patient'],'doctor_id'=>$c['doctor'],'appointment_type_id'=>$c['type'],'appointment_number'=>'APT-000001','starts_at'=>'2026-09-12 09:00:00','ends_at'=>'2026-09-12 09:30:00','status'=>'scheduled','source'=>'clinic','created_by'=>$c['owner']->id,'updated_by'=>$c['owner']->id,'created_at'=>now(),'updated_at'=>now()]);
        $this->postJson(self::ROOT,$this->data($c,['appointment_id'=>$id]))->assertCreated()->assertJsonPath('data.appointment.id',$id);
        $this->postJson(self::ROOT,$this->data($c,['appointment_id'=>99999]))->assertUnprocessable();
        $patient=$this->postJson('/api/v1/clinic/patients',['first_name'=>'Other','last_name'=>'Patient','gender'=>'male'])->json('data.id');
        $this->postJson(self::ROOT,$this->data($c,['appointment_id'=>$id,'patient_id'=>$patient]))->assertUnprocessable();
    }

    public function test_catalog_linkage_closed_states_and_dispensing_permissions(): void {
        $c=$this->clinic();
        $med=$this->postJson('/api/v1/clinic/medications',['name'=>'Catalog medicine','generic_name'=>'Generic search','strength'=>'50 mg'])->assertCreated()->json('data.id');
        $this->getJson('/api/v1/clinic/medications/search?search=Generic')->assertOk()->assertJsonPath('data.0.id',$med);
        $data=$this->data($c);$data['items'][0]['medication_id']=$med;
        $p=$this->postJson(self::ROOT,$data)->assertCreated()->assertJsonPath('data.items.0.medication_name','Catalog medicine')->json('data');
        foreach(['completed','expired','dispensed'] as $status) {
            DB::table('prescriptions')->where('id',$p['id'])->update(['status'=>$status]);
            $this->putJson(self::ROOT.'/'.$p['id'],$this->data($c))->assertUnprocessable();
        }
        DB::table('prescriptions')->where('id',$p['id'])->update(['status'=>'active']);
        $c['plan']->update(['features'=>['prescriptions'=>true,'pharmacy'=>false,'multi_branch'=>true]]);
        $this->postJson(self::ROOT.'/'.$p['id'].'/send-to-pharmacy')->assertForbidden();
        $this->postJson(self::ROOT.'/'.$p['id'].'/dispense',['items'=>[['id'=>$p['items'][0]['id'],'dispensed_quantity'=>1]]])->assertForbidden();
        $this->withHeader('X-Branch-Context','99999')->getJson(self::ROOT)->assertStatus(409);
    }
}

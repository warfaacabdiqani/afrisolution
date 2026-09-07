<?php
namespace Tests\Feature;
use App\Models\Plan;
use App\Models\User;
use App\Models\Specialty;
use App\Services\PlatformService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class DoctorManagementTest extends TestCase {
    use RefreshDatabase;
    private const ROOT='/api/v1/clinic/doctors';
    private function clinic(string $slug='alpha'): array {
        $actor=User::factory()->create();$plan=Plan::create(['name'=>'Clinical','branch_limit'=>3,'member_limit'=>10,'doctor_limit'=>50,'trial_days'=>14,'features'=>['clinicians'=>true,'multi_branch'=>true]]);
        $tenant=app(PlatformService::class)->createTenant(['name'=>$slug,'slug'=>$slug,'timezone'=>'Africa/Nairobi','plan_id'=>$plan->id,'owner_name'=>'Owner','owner_email'=>$slug.'@example.test','owner_password'=>'SecurePass12345'],$actor->id);
        $owner=User::where('email',$slug.'@example.test')->firstOrFail();$this->select($owner,$tenant->id);
        $specialty=$this->postJson('/api/v1/clinic/specialties',['name'=>'General Practice'])->assertCreated()->json('data.id');
        $branch=DB::table('branches')->where('tenant_id',$tenant->id)->value('id');return[$tenant,$owner,$plan,$branch,$specialty];
    }
    private function select(User $user,int $tenant):void{$this->flushSession();$this->app['auth']->forgetGuards();$this->withHeader('Origin','http://localhost')->actingAs($user,'web')->postJson('/api/v1/session/clinic',['clinic_id'=>$tenant])->assertOk();}
    private function data(int $branch,int $specialty,array $extra=[]):array{return array_merge(['first_name'=>'Ahmed','last_name'=>'Hassan','specialty_ids'=>[$specialty],'primary_branch_id'=>$branch,'availability_status'=>'available'], $extra);}
    private function days(array $monday=[]):array{return array_map(fn($day)=>array_merge(['day_of_week'=>$day,'is_available'=>$day===1,'start_time'=>'08:00','end_time'=>'16:00','break_start'=>'12:00','break_end'=>'13:00'],$day===1?$monday:[]),range(1,7));}
    public function test_profile_crud_numbers_duplicates_search_stats_and_audit():void {
        $this->getJson(self::ROOT)->assertUnauthorized();
        [$tenant,$owner,$plan,$branch,$specialty]=$this->clinic();
        $this->postJson(self::ROOT,[])->assertUnprocessable();
        $this->postJson(self::ROOT,$this->data($branch,$specialty,['tenant_id'=>999,'consultation_fee'=>-1]))->assertUnprocessable();
        $id=$this->postJson(self::ROOT,$this->data($branch,$specialty,['email'=>'doctor@example.test','notes'=>'Private staff note']))->assertCreated()->assertJsonPath('data.doctor_number','DOC-00001')->json('data.id');
        $this->getJson(self::ROOT.'/'.$id)->assertOk()->assertJsonPath('data.availability','available');
        $this->postJson(self::ROOT,$this->data($branch,$specialty,['email'=>'doctor@example.test']))->assertStatus(409)->assertJsonPath('duplicates.0.id',$id);
        $this->postJson(self::ROOT,$this->data($branch,$specialty,['email'=>'doctor@example.test','confirm_duplicate'=>true]))->assertCreated()->assertJsonPath('data.doctor_number','DOC-00002');
        $this->getJson(self::ROOT.'?search=Ahmed%20Hassan&status=active&availability=available')->assertOk()->assertJsonCount(2,'data')->assertJsonPath('stats.active',2)->assertJsonPath('stats.specializations',1)->assertJsonMissingPath('data.0.notes');
        $this->getJson(self::ROOT.'?search=Practice')->assertOk()->assertJsonCount(2,'data');
        $this->putJson(self::ROOT.'/'.$id,$this->data($branch,$specialty,['qualification'=>'MBBS','availability_status'=>'in_consultation']))->assertOk();
        $this->getJson(self::ROOT.'/'.$id)->assertJsonPath('data.qualification','MBBS')->assertJsonPath('data.availability','in_consultation');
        $this->postJson(self::ROOT.'/'.$id.'/deactivate')->assertOk()->assertJsonPath('data.status','inactive');
        $this->getJson(self::ROOT.'/'.$id)->assertJsonPath('data.availability','unavailable');
        $this->postJson(self::ROOT.'/'.$id.'/activate')->assertOk();
        $this->getJson('/api/v1/clinic/dashboard')->assertOk()->assertJsonPath('data.stats.active_doctors',2);
        $this->getJson(self::ROOT.'/'.$id.'/activity')->assertOk()->assertJsonPath('data.data.0.action','doctor.activated');
        $this->assertStringNotContainsString('Private staff note',DB::table('platform_audit_logs')->where('tenant_id',$tenant->id)->get()->toJson());
    }
    public function test_branch_and_tenant_isolation():void {
        [$a,$owner,$plan,$main,$specialty]=$this->clinic();$second=DB::table('branches')->insertGetId(['tenant_id'=>$a->id,'name'=>'Second']);
        $id=$this->postJson(self::ROOT,$this->data($main,$specialty,['branch_ids'=>[$second]]))->assertCreated()->json('data.id');
        [$b,$other,$otherPlan,$foreignBranch,$foreignSpecialty]=$this->clinic('beta');
        $this->getJson(self::ROOT.'/'.$id)->assertNotFound();$this->putJson(self::ROOT.'/'.$id,$this->data($foreignBranch,$foreignSpecialty))->assertNotFound();
        $this->select($owner,$a->id);
        $this->postJson(self::ROOT,$this->data($foreignBranch,$specialty))->assertUnprocessable();
        $this->postJson(self::ROOT,$this->data($main,$foreignSpecialty))->assertUnprocessable();
        $membership=DB::table('tenant_memberships')->where('tenant_id',$a->id)->where('user_id',$owner->id)->value('id');
        DB::table('tenant_memberships')->where('id',$membership)->update(['all_branches'=>false]);DB::table('branch_memberships')->insert(['tenant_id'=>$a->id,'membership_id'=>$membership,'branch_id'=>$main]);
        $this->getJson(self::ROOT.'/'.$id)->assertOk()->assertJsonCount(1,'data.branches')->assertJsonPath('data.can_manage_branches',false);
        $this->putJson(self::ROOT.'/'.$id,$this->data($main,$specialty))->assertForbidden();
        $this->getJson(self::ROOT.'?branch_id='.$second)->assertForbidden();
        $this->putJson(self::ROOT.'/'.$id.'/schedule',['branch_id'=>$second,'days'=>$this->days()])->assertForbidden();
        $this->postJson(self::ROOT.'/'.$id.'/leaves',['start_date'=>'2026-09-07','end_date'=>'2026-09-08'])->assertForbidden();
        $this->postJson(self::ROOT,$this->data($main,$specialty,['branch_ids'=>[$second]]))->assertUnprocessable();
        DB::table('tenant_memberships')->where('id',$membership)->update(['permissions'=>json_encode(['doctors.view'])]);
        $this->putJson(self::ROOT.'/'.$id,$this->data($main,$specialty))->assertForbidden();
        $this->postJson(self::ROOT.'/'.$id.'/deactivate')->assertForbidden();
        $plan->update(['features'=>['clinicians'=>false]]);$this->getJson(self::ROOT)->assertForbidden();
        $plan->update(['features'=>['clinicians'=>true]]);DB::table('subscriptions')->where('tenant_id',$a->id)->update(['status'=>'suspended']);$this->getJson(self::ROOT)->assertForbidden();
    }
    public function test_schedules_breaks_leave_and_availability():void {
        $this->travelTo(now()->setDate(2026,9,7)->setTime(9,0));
        [$tenant,$owner,$plan,$branch,$specialty]=$this->clinic();$other=DB::table('branches')->insertGetId(['tenant_id'=>$tenant->id,'name'=>'Second']);
        $id=$this->postJson(self::ROOT,$this->data($branch,$specialty,['branch_ids'=>[$other]]))->assertCreated()->json('data.id');
        $this->getJson(self::ROOT.'/'.$id.'/schedule?branch_id='.$branch)->assertOk()->assertJsonCount(0,'data');
        $this->putJson(self::ROOT.'/'.$id.'/schedule',['branch_id'=>$branch,'days'=>$this->days(['start_time'=>'17:00'])])->assertUnprocessable();
        $this->putJson(self::ROOT.'/'.$id.'/schedule',['branch_id'=>$branch,'days'=>$this->days(['break_end'=>'18:00'])])->assertUnprocessable();
        $this->putJson(self::ROOT.'/'.$id.'/schedule',['branch_id'=>$branch,'days'=>$this->days(['branch_id'=>$other])])->assertUnprocessable();
        $this->putJson(self::ROOT.'/'.$id.'/schedule',['branch_id'=>$branch,'days'=>$this->days()])->assertNoContent();
        $this->getJson(self::ROOT.'/'.$id.'/schedule?branch_id='.$branch)->assertJsonCount(1,'data');
        $this->putJson(self::ROOT.'/'.$id.'/schedule',['branch_id'=>$other,'days'=>$this->days()])->assertUnprocessable();
        $leave=$this->postJson(self::ROOT.'/'.$id.'/leaves',['branch_id'=>$branch,'start_date'=>'2026-09-07','end_date'=>'2026-09-09','reason'=>'Private leave reason'])->assertCreated()->json('data.id');
        $this->getJson(self::ROOT.'/'.$id)->assertJsonPath('data.status','active')->assertJsonPath('data.availability','on_leave');
        $this->getJson(self::ROOT.'?availability=on_leave')->assertOk()->assertJsonCount(1,'data')->assertJsonPath('stats.on_leave',1);
        $this->getJson(self::ROOT.'/'.$id.'/leaves')->assertJsonPath('data.data.0.status','active');
        $this->postJson(self::ROOT.'/'.$id.'/leaves',['branch_id'=>$branch,'start_date'=>'2026-09-08','end_date'=>'2026-09-07'])->assertUnprocessable();
        $this->postJson(self::ROOT.'/'.$id.'/leaves',['branch_id'=>$branch,'start_date'=>'2026-09-08','end_date'=>'2026-09-10'])->assertUnprocessable();
        $this->postJson(self::ROOT.'/'.$id.'/leaves/'.$leave.'/cancel')->assertNoContent();
        $this->getJson(self::ROOT.'/'.$id)->assertJsonPath('data.availability','available');
        $this->assertStringNotContainsString('Private leave reason',DB::table('platform_audit_logs')->get()->toJson());
    }
    public function test_account_linking_quota_and_permissions():void {
        [$tenant,$owner,$plan,$branch,$specialty]=$this->clinic();$plan->update(['doctor_limit'=>1]);
        $data=$this->data($branch,$specialty,['account_mode'=>'create','account_email'=>'newdoctor@example.test','password'=>'NewDoctorPass123','password_confirmation'=>'NewDoctorPass123']);
        $id=$this->postJson(self::ROOT,$data)->assertCreated()->assertJsonMissingPath('data.password')->json('data.id');
        $this->getJson(self::ROOT.'/options')->assertOk()->assertJsonPath('data.usage',1);
        $this->postJson(self::ROOT,$this->data($branch,$specialty))->assertUnprocessable()->assertJsonValidationErrors('plan');
        $this->postJson(self::ROOT.'/'.$id.'/deactivate')->assertOk();
        $this->postJson(self::ROOT,$this->data($branch,$specialty))->assertUnprocessable();
        $newUser=User::where('email','newdoctor@example.test')->firstOrFail();$this->assertNotEquals('NewDoctorPass123',$newUser->password);$this->assertFalse($newUser->is_platform_admin);
        $this->assertDatabaseHas('tenant_memberships',['user_id'=>$newUser->id,'tenant_id'=>$tenant->id,'all_branches'=>false]);
        $this->select($newUser,$tenant->id);$this->getJson(self::ROOT.'/'.$id)->assertOk();$this->putJson(self::ROOT.'/'.$id,$this->data($branch,$specialty))->assertForbidden();
        $this->getJson('/api/v1/platform/dashboard')->assertForbidden();
        $this->select($owner,$tenant->id);$plan->update(['doctor_limit'=>10]);
        [$foreign,$foreignOwner]=$this->clinic('beta');$this->select($owner,$tenant->id);
        $this->postJson(self::ROOT,$this->data($branch,$specialty,['account_mode'=>'existing','user_id'=>$foreignOwner->id]))->assertUnprocessable();
        $this->postJson(self::ROOT,$this->data($branch,$specialty,['account_mode'=>'existing','user_id'=>$newUser->id]))->assertUnprocessable();
        $this->postJson(self::ROOT,$this->data($branch,$specialty,['account_mode'=>'existing','user_id'=>$owner->id]))->assertCreated();
    }
    public function test_future_availability_respects_timezone_schedule_break_and_leave():void {
        [$tenant,$owner,$plan,$branch,$specialty]=$this->clinic();
        $id=$this->postJson(self::ROOT,$this->data($branch,$specialty))->assertCreated()->json('data.id');
        $this->putJson(self::ROOT.'/'.$id.'/schedule',['branch_id'=>$branch,'days'=>$this->days()])->assertNoContent();
        $this->postJson(self::ROOT.'/'.$id.'/leaves',['branch_id'=>$branch,'start_date'=>'2026-09-14','end_date'=>'2026-09-14'])->assertCreated();
        app(\App\Tenancy\TenantContext::class)->set($tenant->id);
        try {
            $doctor=\App\Models\Doctor::findOrFail($id);
            $context=['clinic'=>(object)['id'=>$tenant->id,'timezone'=>'Africa/Nairobi'],'branches'=>DB::table('branches')->where('tenant_id',$tenant->id)->get(),'today'=>'2026-09-07'];
            $service=app(\App\Services\DoctorService::class);
            // UTC timestamps are checked against the clinic's local working hours.
            foreach(['2026-09-07 04:59:00'=>false,'2026-09-07 05:00:00'=>true,'2026-09-07 09:00:00'=>false,'2026-09-07 10:00:00'=>true,'2026-09-07 13:00:00'=>false,'2026-09-08 06:00:00'=>false,'2026-09-14 06:00:00'=>false] as $time=>$expected) {
                $this->assertSame($expected,$service->availableAt($context,$doctor,$branch,\Illuminate\Support\Carbon::parse($time,'UTC')),$time);
            }
        } finally {app(\App\Tenancy\TenantContext::class)->clear();}
    }
    public function test_pagination_and_legacy_seats_do_not_double_count():void {
        [$tenant,$owner,$plan,$branch,$specialty]=$this->clinic();
        $legacy=User::factory()->create();DB::table('tenant_memberships')->insert(['tenant_id'=>$tenant->id,'user_id'=>$legacy->id,'role'=>'doctor','status'=>'active','all_branches'=>true]);
        $plan->update(['doctor_limit'=>1]);
        $this->postJson(self::ROOT,$this->data($branch,$specialty,['account_mode'=>'existing','user_id'=>$legacy->id]))->assertCreated();
        $this->getJson(self::ROOT.'/options')->assertJsonPath('data.usage',1);
        $plan->update(['doctor_limit'=>30]);
        for($i=0;$i<25;$i++)$this->postJson(self::ROOT,$this->data($branch,$specialty,['first_name'=>'Doctor '.$i]))->assertCreated();
        $this->getJson(self::ROOT.'?per_page=25')->assertOk()->assertJsonCount(25,'data')->assertJsonPath('meta.total',26);
        $this->getJson(self::ROOT.'?per_page=25&page=2')->assertJsonCount(1,'data');
    }
}

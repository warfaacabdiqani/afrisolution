<?php
namespace Tests\Feature;
use App\Models\{BusinessType, Plan, User, SalonService};
use App\Services\{PlatformService, ClinicSettingsService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SalonBookingTest extends TestCase
{
    use RefreshDatabase;
    private const ROOT='/api/v1/salon/appointments';
    protected function setUp(): void { parent::setUp(); $this->travelTo(Carbon::parse('2026-10-07 06:00:00','UTC')); }
    private function workspace(string $name='one',string $type='beauty-salon'): array {
        $this->seed(\Database\Seeders\BusinessTypeSeeder::class);
        $actor=User::factory()->create();
        $plan=Plan::create(['name'=>'Bookings','branch_limit'=>3,'member_limit'=>10,'trial_days'=>14,'features'=>array_fill_keys(['clients','services','salon_staff','appointments','billing','multi_branch'],true)]);
        $tenant=app(PlatformService::class)->createTenant(['name'=>$name,'timezone'=>'Africa/Nairobi','business_type_id'=>BusinessType::where('slug',$type)->value('id'),'plan_id'=>$plan->id,'owner_name'=>'Owner','owner_email'=>$name.'@example.test','owner_password'=>'SecurePass12345'],$actor->id);
        $user=User::where('email',$name.'@example.test')->firstOrFail();
        $branch=DB::table('branches')->where('tenant_id',$tenant->id)->value('id');
        $this->flushSession(); $this->app['auth']->forgetGuards();
        $this->withHeader('Origin','http://localhost')->actingAs($user)->postJson('/api/v1/session/clinic',['clinic_id'=>$tenant->id])->assertOk();
        return compact('tenant','user','branch','plan');
    }
    private function days(): array { return array_map(fn($day)=>['day_of_week'=>$day,'is_available'=>true,'start_time'=>'08:00','end_time'=>'18:00','break_start'=>'12:00','break_end'=>'13:00'],range(1,7)); }
    private function fixture(string $name='one'): array {
        $c=$this->workspace($name);
        $client=$this->postJson('/api/v1/salon/clients',['first_name'=>'Amina','last_name'=>'Ali','phone'=>'0700112233','status'=>'active'])->assertCreated()->json('data.id');
        $stylist=$this->postJson('/api/v1/salon/stylists',['user_id'=>$c['user']->id,'display_name'=>'Asha','title'=>'Hair Stylist','status'=>'active','branch_ids'=>[$c['branch']]])->assertCreated()->json('data.id');
        $category=$this->postJson('/api/v1/salon/service-categories',['name'=>'Hair','status'=>'active','sort_order'=>1])->assertCreated()->json('data.id');
        $service=$this->postJson('/api/v1/salon/services',['name'=>'Haircut','status'=>'active','service_category_id'=>$category,'duration_minutes'=>30,'price'=>15,'requires_deposit'=>true,'deposit_amount'=>5,'branch_ids'=>[$c['branch']],'stylist_ids'=>[$stylist]])->assertCreated()->json('data.id');
        $color=$this->postJson('/api/v1/salon/services',['name'=>'Color','status'=>'active','service_category_id'=>$category,'duration_minutes'=>60,'price'=>30,'requires_deposit'=>false,'branch_ids'=>[$c['branch']],'stylist_ids'=>[$stylist]])->assertCreated()->json('data.id');
        $this->putJson('/api/v1/salon/location-hours',['branch_id'=>$c['branch'],'days'=>$this->days()])->assertOk();
        $this->putJson('/api/v1/salon/stylists/'.$stylist.'/schedule',['branch_id'=>$c['branch'],'days'=>$this->days()])->assertOk();
        return $c+compact('client','stylist','service','color');
    }
    private function data(array $c,array $extra=[]): array { return array_replace(['branch_id'=>$c['branch'],'client_id'=>$c['client'],'stylist_id'=>$c['stylist'],'service_ids'=>[$c['service']],'date'=>'2026-10-07','start_time'=>'10:00','source'=>'reception'],$extra); }
    private function create(array $c,array $extra=[]): int { return $this->postJson(self::ROOT,$this->data($c,$extra))->assertCreated()->json('data.id'); }

    public function test_multi_service_duration_prices_lifecycle_and_shared_billing(): void {
        $c=$this->fixture(); $id=$this->create($c,['service_ids'=>[$c['service'],$c['color']]]);
        $this->getJson(self::ROOT.'/'.$id)->assertOk()->assertJsonPath('data.appointment_number','APT-000001')->assertJsonPath('data.ends_at','2026-10-07 11:30:00')->assertJsonPath('data.duration_minutes',90)->assertJsonCount(2,'data.items');
        DB::table('salon_services')->where('id',$c['service'])->update(['price'=>50,'duration_minutes'=>45]);
        $this->putJson(self::ROOT.'/'.$id,$this->data($c,['service_ids'=>[$c['service'],$c['color']],'notes'=>'Keep snapshots']))->assertOk()->assertJsonPath('data.duration_minutes',90);
        $this->assertDatabaseHas('salon_appointments',['id'=>$id,'total'=>45,'deposit_required'=>5]);
        $this->postJson(self::ROOT.'/'.$id.'/invoice')->assertUnprocessable();
        foreach(['confirm'=>'confirmed','check-in'=>'checked_in','start-service'=>'in_service','complete'=>'completed'] as $action=>$status) $this->postJson(self::ROOT.'/'.$id.'/'.$action)->assertOk()->assertJsonPath('data.status',$status);
        $invoice=$this->postJson(self::ROOT.'/'.$id.'/invoice')->assertCreated()->json('data.id');
        $this->postJson(self::ROOT.'/'.$id.'/invoice')->assertCreated()->assertJsonPath('data.id',$invoice);
        $this->assertDatabaseCount('billing_invoices',1); $this->assertDatabaseHas('billing_invoice_items',['invoice_id'=>$invoice,'description'=>'Haircut','unit_price'=>15]);
        $payment=['amount'=>20,'method'=>'cash','idempotency_key'=>'one'];
        $this->postJson('/api/v1/billing/invoices/'.$invoice.'/payments',$payment)->assertOk()->assertJsonPath('data.status','partial');
        $this->postJson('/api/v1/billing/invoices/'.$invoice.'/payments',$payment)->assertOk(); $this->assertDatabaseCount('billing_payments',1);
        $this->postJson('/api/v1/billing/invoices/'.$invoice.'/payments',['amount'=>26,'method'=>'cash','idempotency_key'=>'two'])->assertUnprocessable();
        $this->postJson('/api/v1/billing/invoices/'.$invoice.'/payments',['amount'=>25,'method'=>'cash','idempotency_key'=>'two'])->assertOk()->assertJsonPath('data.status','paid');
        $widgets=collect($this->getJson('/api/v1/clinic/dashboard')->assertOk()->json('data.widgets'))->keyBy('key');
        $this->assertEquals(45,$widgets['monthly_revenue']['value']); $this->assertEquals(1,$widgets['today_appointments']['value']);
        $this->postJson(self::ROOT.'/'.$id.'/cancel',['reason'=>'Too late'])->assertUnprocessable();
        $this->putJson(self::ROOT.'/'.$id,$this->data($c))->assertUnprocessable();
    }

    public function test_conflicts_schedules_breaks_time_off_and_reschedule(): void {
        $c=$this->fixture(); $id=$this->create($c);
        $this->postJson(self::ROOT,$this->data($c,['start_time'=>'10:15']))->assertUnprocessable()->assertJsonValidationErrors('start_time');
        $second=$this->create($c,['start_time'=>'10:30']);
        $this->postJson(self::ROOT,$this->data($c,['start_time'=>'12:00']))->assertUnprocessable();
        $this->postJson(self::ROOT,$this->data($c,['start_time'=>'18:00']))->assertUnprocessable();
        $this->postJson(self::ROOT.'/'.$id.'/reschedule',$this->data($c,['start_time'=>'14:00']))->assertOk()->assertJsonPath('data.starts_at','2026-10-07 14:00:00');
        $this->postJson('/api/v1/salon/stylists/'.$c['stylist'].'/time-off',['branch_id'=>$c['branch'],'starts_at'=>'2026-10-07 14:00','ends_at'=>'2026-10-07 15:00','kind'=>'leave'])->assertUnprocessable();
        $off=$this->postJson('/api/v1/salon/stylists/'.$c['stylist'].'/time-off',['branch_id'=>$c['branch'],'starts_at'=>'2026-10-07 15:00','ends_at'=>'2026-10-07 16:00','kind'=>'leave'])->assertCreated()->json('data.id');
        $this->postJson(self::ROOT,$this->data($c,['start_time'=>'15:00']))->assertUnprocessable();
        $this->postJson('/api/v1/salon/stylists/'.$c['stylist'].'/time-off/'.$off.'/cancel',['branch_id'=>$c['branch']])->assertNoContent();
        $this->create($c,['start_time'=>'15:00']);
        $slots=$this->getJson(self::ROOT.'/available-slots?'.http_build_query($this->data($c)+['appointment_id'=>$id]))->assertOk()->json('data.slots');
        $this->assertContains('14:00',$slots); $this->assertNotContains('10:30',$slots); $this->assertNotContains('12:00',$slots);
        $this->postJson(self::ROOT.'/'.$second.'/cancel',[])->assertUnprocessable();
        $this->postJson(self::ROOT.'/'.$second.'/cancel',['reason'=>'Client request'])->assertOk()->assertJsonPath('data.status','cancelled');
        $this->create($c,['start_time'=>'10:30']);
        $this->assertDatabaseHas('platform_audit_logs',['action'=>'salon.appointment.rescheduled']);
    }

    public function test_walk_in_policy_discounts_and_validation(): void {
        $c=$this->fixture();
        foreach(['client_id','stylist_id','branch_id'] as $field) $this->postJson(self::ROOT,$this->data($c,[$field=>999999]))->assertStatus($field==='branch_id'?403:422);
        $this->postJson(self::ROOT,$this->data($c,['service_ids'=>[999999]]))->assertUnprocessable();
        DB::table('salon_service_staff')->where('service_id',$c['color'])->delete();
        $this->postJson(self::ROOT,$this->data($c,['service_ids'=>[$c['color']]]))->assertUnprocessable();
        $this->postJson(self::ROOT,$this->data($c,['discount'=>16]))->assertUnprocessable();
        $id=$this->create($c,['start_time'=>'09:00','source'=>'walk_in','discount'=>5]);
        $this->getJson(self::ROOT.'/'.$id)->assertJsonPath('data.status','waiting'); $this->assertDatabaseHas('salon_appointments',['id'=>$id,'total'=>10]);
        app(ClinicSettingsService::class)->set($c['tenant']->id,'salon',['allow_walk_in'=>false],$c['user']->id);
        $this->postJson(self::ROOT,$this->data($c,['source'=>'walk_in','start_time'=>'11:00']))->assertUnprocessable();
        $this->postJson(self::ROOT,$this->data($c,['override_conflict'=>true,'override_reason'=>'No']))->assertForbidden();
        $this->postJson(self::ROOT,$this->data($c,['patient_id'=>1,'duration_minutes'=>1,'total'=>0]))->assertUnprocessable();
    }

    public function test_tenant_isolation_and_business_and_action_permissions(): void {
        $a=$this->fixture('a'); $id=$this->create($a); $b=$this->fixture('b');
        $this->getJson(self::ROOT.'/'.$id)->assertNotFound();
        $this->postJson(self::ROOT.'/'.$id.'/check-in')->assertNotFound();
        $this->postJson(self::ROOT,$this->data($b,['client_id'=>$a['client']]))->assertUnprocessable();
        $this->postJson(self::ROOT,$this->data($b,['stylist_id'=>$a['stylist']]))->assertUnprocessable();
        $this->postJson(self::ROOT,$this->data($b,['service_ids'=>[$a['service']]]))->assertUnprocessable();
        $own=$this->create($b);
        DB::table('tenant_memberships')->where('tenant_id',$b['tenant']->id)->update(['permissions'=>json_encode(['appointments.view','appointments.view_all'])]);
        $this->getJson(self::ROOT.'/'.$own)->assertOk(); $this->postJson(self::ROOT,$this->data($b))->assertForbidden();
        foreach(['check-in','start-service','complete','cancel'] as $action) $this->postJson(self::ROOT.'/'.$own.'/'.$action,['reason'=>'Denied'])->assertForbidden();
        $this->postJson(self::ROOT.'/'.$own.'/invoice')->assertForbidden();
        foreach(['clinic','dental','stadium'] as $type) { $this->workspace($type,$type); $this->getJson(self::ROOT.'/options')->assertForbidden()->assertJsonPath('code','BUSINESS_MODULE_UNAVAILABLE'); }
    }
}

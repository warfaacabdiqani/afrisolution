<?php
namespace Tests\Feature;
use App\Models\{BusinessType,Plan,Tenant,User};
use App\Services\PlatformService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
class SalonCoreTest extends TestCase
{
    use RefreshDatabase;
    private function salon(string $slug='salon',string $type='beauty-salon'): array {
        $this->seed(\Database\Seeders\BusinessTypeSeeder::class);
        $actor=User::factory()->create();
        $plan=Plan::create(['name'=>'Salon Pro','branch_limit'=>3,'member_limit'=>10,'trial_days'=>14,'features'=>['clients'=>true,'services'=>true,'salon_staff'=>true,'multi_branch'=>true]]);
        $tenant=app(PlatformService::class)->createTenant(['name'=>$slug,'slug'=>$slug,'business_type_id'=>BusinessType::where('slug',$type)->value('id'),'timezone'=>'Africa/Nairobi','plan_id'=>$plan->id,'owner_name'=>'Salon Owner','owner_email'=>$slug.'@example.test','owner_password'=>'TestPassword123'],$actor->id);
        $owner=User::where('email',$slug.'@example.test')->firstOrFail();
        return [$tenant,$owner,$plan,(int)DB::table('branches')->where('tenant_id',$tenant->id)->value('id')];
    }
    private function select(array $s): void { $this->flushSession();$this->app['auth']->forgetGuards();$this->withHeader('Origin','http://localhost')->actingAs($s[1],'web')->postJson('/api/v1/session/clinic',['clinic_id'=>$s[0]->id])->assertOk(); }
    private function client(array $overrides=[]): array { return array_replace(['first_name'=>'Amina','last_name'=>'Ali','status'=>'active','notes'=>'Private preference'],$overrides); }
    private function stylist(array $s,array $overrides=[]): array { return array_replace(['user_id'=>$s[1]->id,'display_name'=>'Stylist A','title'=>'Hair Stylist','status'=>'active','branch_ids'=>[$s[3]]],$overrides); }
    private function category(): int { return $this->postJson('/api/v1/salon/service-categories',['name'=>'Hair','sort_order'=>0,'status'=>'active'])->assertCreated()->json('data.id'); }
    private function service(int $category,array $branches,array $stylists=[]): array { return ['name'=>'Haircut','service_category_id'=>$category,'duration_minutes'=>30,'price'=>25,'status'=>'active','requires_deposit'=>true,'deposit_amount'=>5,'branch_ids'=>$branches,'stylist_ids'=>$stylists]; }

    public function test_client_crud_numbering_archive_statistics_and_audit(): void {
        $s=$this->salon();$this->select($s);
        $stylist=$this->postJson('/api/v1/salon/stylists',$this->stylist($s))->assertCreated()->json('data.id');
        $client=$this->postJson('/api/v1/salon/clients',$this->client(['preferred_stylist_id'=>$stylist]))->assertCreated()->assertJsonPath('data.client_number','CLI-000001')->json('data');
        $this->postJson('/api/v1/salon/clients',$this->client())->assertCreated()->assertJsonPath('data.client_number','CLI-000002');
        $this->putJson('/api/v1/salon/clients/'.$client['id'],$this->client(['phone'=>'123456']))->assertOk()->assertJsonPath('data.phone','123456');
        $this->getJson('/api/v1/salon/clients/'.$client['id'])->assertOk()->assertJsonPath('data.history_available',false)->assertJsonCount(2,'activity');
        $this->getJson('/api/v1/salon/clients?search=Amina&per_page=1')->assertOk()->assertJsonPath('meta.total',2)->assertJsonCount(1,'data')->assertJsonPath('stats.month',2);
        $this->postJson('/api/v1/salon/clients/'.$client['id'].'/archive')->assertOk()->assertJsonPath('data.status','archived');
        $this->deleteJson('/api/v1/salon/clients/'.$client['id'])->assertStatus(405);
        $this->getJson('/api/v1/salon/clients')->assertJsonPath('stats.active',1)->assertJsonPath('stats.inactive',1);
        $this->assertDatabaseHas('platform_audit_logs',['tenant_id'=>$s[0]->id,'action'=>'client.archived']);
        $metadata=DB::table('platform_audit_logs')->where('action','client.created')->value('metadata');
        $this->assertStringNotContainsString('Private preference',$metadata);$this->assertStringNotContainsString('Amina',$metadata);
        $this->postJson('/api/v1/salon/clients',$this->client(['tenant_id'=>$s[0]->id,'client_number'=>'HACK']))->assertUnprocessable()->assertJsonValidationErrors(['tenant_id','client_number']);
    }
    public function test_service_categories_staff_links_and_multiple_stylist_assignments(): void {
        $s=$this->salon();$this->select($s);$category=$this->category();
        $a=$this->postJson('/api/v1/salon/stylists',$this->stylist($s))->assertCreated()->assertJsonPath('data.staff_number','STY-000001')->json('data.id');
        $user=User::factory()->create();DB::table('tenant_memberships')->insert(['tenant_id'=>$s[0]->id,'user_id'=>$user->id,'role'=>'staff','status'=>'active','all_branches'=>true]);
        $b=$this->postJson('/api/v1/salon/stylists',$this->stylist($s,['user_id'=>$user->id,'display_name'=>'Stylist B']))->assertCreated()->json('data.id');
        $data=$this->service($category,[$s[3]],[$a,$b]);
        $service=$this->postJson('/api/v1/salon/services',$data)->assertCreated()->assertJsonCount(2,'data.stylists')->json('data.id');
        $this->getJson('/api/v1/salon/stylists/'.$a)->assertJsonCount(1,'data.services');
        $this->putJson('/api/v1/salon/services/'.$service,array_replace($data,['price'=>30,'stylist_ids'=>[$b]]))->assertOk()->assertJsonCount(1,'data.stylists');
        $this->putJson('/api/v1/salon/service-categories/'.$category,['name'=>'Hair Care','status'=>'active','sort_order'=>1])->assertOk();
        $this->putJson('/api/v1/salon/stylists/'.$a,$this->stylist($s,['display_name'=>'Updated Stylist']))->assertOk();
        $this->postJson('/api/v1/salon/services/'.$service.'/archive')->assertOk();
        foreach(['salon_staff.created','salon_staff.updated','service.created','service.updated','service.archived','service_category.created','service_category.updated']as $event)$this->assertDatabaseHas('platform_audit_logs',['action'=>$event,'tenant_id'=>$s[0]->id]);
        $this->postJson('/api/v1/salon/stylists',$this->stylist($s))->assertUnprocessable();
    }
    public function test_tenant_and_branch_isolation_and_reference_validation(): void {
        $a=$this->salon('a');$b=$this->salon('b');$this->select($b);
        $foreignClient=$this->postJson('/api/v1/salon/clients',$this->client())->assertCreated()->json('data.id');
        $foreignStylist=$this->postJson('/api/v1/salon/stylists',$this->stylist($b))->assertCreated()->json('data.id');$foreignCategory=$this->category();
        $foreignService=$this->postJson('/api/v1/salon/services',$this->service($foreignCategory,[$b[3]],[$foreignStylist]))->assertCreated()->json('data.id');
        $this->select($a);
        foreach(['clients'=>$foreignClient,'stylists'=>$foreignStylist,'services'=>$foreignService,'service-categories'=>$foreignCategory]as $path=>$id)$this->getJson('/api/v1/salon/'.$path.'/'.$id)->assertNotFound();
        $this->postJson('/api/v1/salon/clients',$this->client(['preferred_stylist_id'=>$foreignStylist]))->assertUnprocessable();
        $this->postJson('/api/v1/salon/stylists',$this->stylist($a,['user_id'=>$b[1]->id]))->assertUnprocessable();
        $this->postJson('/api/v1/salon/stylists',$this->stylist($a,['branch_ids'=>[$a[3],$b[3]]]))->assertUnprocessable();
        $this->postJson('/api/v1/salon/services',$this->service($foreignCategory,[$a[3]]))->assertUnprocessable();
        $category=$this->category();$this->postJson('/api/v1/salon/services',$this->service($category,[$a[3]],[$foreignStylist]))->assertUnprocessable();
        $local=$this->postJson('/api/v1/salon/clients',$this->client())->assertCreated()->json('data.id');
        $second=DB::table('branches')->insertGetId(['tenant_id'=>$a[0]->id,'name'=>'Second','status'=>'active']);
        $this->postJson('/api/v1/clinic/branch',['branch_id'=>$second])->assertOk();
        $this->getJson('/api/v1/salon/clients/'.$local)->assertNotFound();$this->getJson('/api/v1/salon/clients')->assertJsonCount(0,'data');
        $this->withHeader('X-Branch-Context',(string)$a[3])->getJson('/api/v1/salon/clients')->assertStatus(409);
    }
    public function test_business_permission_plan_limits_and_settings_enforcement(): void {
        foreach(['clinic','dental','stadium']as $type){$s=$this->salon($type,$type);$this->select($s);foreach(['clients','stylists','services','service-categories']as $path)$this->getJson('/api/v1/salon/'.$path)->assertForbidden()->assertJsonPath('code','BUSINESS_MODULE_UNAVAILABLE');}
        $s=$this->salon();$this->select($s);
        $this->getJson('/api/v1/clinic/context')->assertOk();
        $sections=$this->getJson('/api/v1/clinic/settings')->assertOk()->json('data.sections');$this->assertArrayHasKey('salon',$sections);
        $values=$sections['salon']['values'];$values['default_duration']=45;$this->putJson('/api/v1/clinic/settings/salon',$values)->assertOk()->assertJsonPath('data.default_duration',45);
        $features=$s[2]->features;$s[2]->update(['features'=>array_replace($features,['clients'=>false])]);
        $this->postJson('/api/v1/salon/clients',$this->client())->assertForbidden()->assertJsonPath('code','PLAN_FEATURE_UNAVAILABLE');
        $s[2]->update(['features'=>$features,'client_limit'=>1,'service_limit'=>1]);
        DB::table('tenant_memberships')->where('tenant_id',$s[0]->id)->update(['permissions'=>json_encode(['clients.view'])]);
        $this->getJson('/api/v1/salon/clients')->assertOk();$this->postJson('/api/v1/salon/clients',$this->client())->assertForbidden()->assertJsonPath('code','PERMISSION_DENIED');
        DB::table('tenant_memberships')->where('tenant_id',$s[0]->id)->update(['permissions'=>null]);
        $id=$this->postJson('/api/v1/salon/clients',$this->client())->assertCreated()->json('data.id');$this->postJson('/api/v1/salon/clients/'.$id.'/archive')->assertOk();
        $this->postJson('/api/v1/salon/clients',$this->client())->assertUnprocessable()->assertJsonValidationErrors('plan');
        $category=$this->category();$this->postJson('/api/v1/salon/services',$this->service($category,[$s[3]]))->assertCreated();$this->postJson('/api/v1/salon/services',$this->service($category,[$s[3]]))->assertUnprocessable()->assertJsonValidationErrors('plan');
    }
    public function test_staff_membership_locations_and_service_values_are_validated(): void {
        $s=$this->salon();$this->select($s);$second=DB::table('branches')->insertGetId(['tenant_id'=>$s[0]->id,'name'=>'Second','status'=>'active']);
        $member=DB::table('tenant_memberships')->where('tenant_id',$s[0]->id)->first();
        DB::table('tenant_memberships')->where('id',$member->id)->update(['all_branches'=>false]);DB::table('branch_memberships')->insert(['tenant_id'=>$s[0]->id,'membership_id'=>$member->id,'branch_id'=>$s[3]]);
        $this->postJson('/api/v1/salon/stylists',$this->stylist($s,['branch_ids'=>[$s[3],$second]]))->assertUnprocessable();
        $this->postJson('/api/v1/salon/stylists',$this->stylist($s,['commission_type'=>'percentage','commission_value'=>101]))->assertUnprocessable();
        $category=$this->category();$data=$this->service($category,[$s[3]]);
        $this->postJson('/api/v1/salon/services',array_replace($data,['deposit_amount'=>26,'duration_minutes'=>0,'price'=>-1]))->assertUnprocessable();
        $this->postJson('/api/v1/salon/clients',$this->client(['date_of_birth'=>'2099-01-01']))->assertUnprocessable();
    }

    public function test_options_navigation_and_shared_location_edit_protection(): void {
        $s=$this->salon();$this->select($s);
        foreach(['clients','stylists','services','service-categories']as $path)$this->getJson('/api/v1/salon/'.$path.'/options')->assertOk();
        $context=$this->getJson('/api/v1/clinic/context')->assertOk()->json('data');
        foreach(['clients','stylists','services']as $key)$this->assertTrue(collect($context['modules'])->firstWhere('key',$key)['allowed']);
        $this->assertFalse(collect($context['modules'])->firstWhere('key','appointments')['allowed']);
        $second=DB::table('branches')->insertGetId(['tenant_id'=>$s[0]->id,'name'=>'Second','status'=>'active']);
        $category=$this->category();
        $stylist=$this->postJson('/api/v1/salon/stylists',$this->stylist($s,['branch_ids'=>[$s[3],$second]]))->assertCreated()->json('data.id');
        $data=$this->service($category,[$s[3],$second],[$stylist]);
        $service=$this->postJson('/api/v1/salon/services',$data)->assertCreated()->json('data.id');
        $member=DB::table('tenant_memberships')->where('tenant_id',$s[0]->id)->first();
        DB::table('tenant_memberships')->where('id',$member->id)->update(['all_branches'=>false]);
        DB::table('branch_memberships')->insert(['tenant_id'=>$s[0]->id,'membership_id'=>$member->id,'branch_id'=>$s[3]]);
        $this->getJson('/api/v1/salon/services/'.$service)->assertOk()->assertJsonPath('data.editable',false)->assertJsonCount(1,'data.branches');
        $this->putJson('/api/v1/salon/services/'.$service,array_replace($data,['branch_ids'=>[$s[3]]]))->assertForbidden()->assertJsonPath('code','PERMISSION_DENIED');
        $this->postJson('/api/v1/salon/services/'.$service.'/archive')->assertForbidden();
        $this->assertDatabaseCount('salon_service_branch',2);
        $this->postJson('/api/v1/clinic/branch',['branch_id'=>$second])->assertForbidden();
    }
}

<?php
/** Test fixture only; callers must verify their isolated database before invoking. */
function salonBookingFixture(): array {
    (new \Database\Seeders\BusinessTypeSeeder)->run();
    $actor=\App\Models\User::factory()->create();
    $plan=\App\Models\Plan::create(['name'=>'Booking Test Pro','branch_limit'=>3,'member_limit'=>10,'trial_days'=>14,'features'=>array_fill_keys(['clients','services','salon_staff','appointments','billing','multi_branch'],true)]);
    $tenant=app(\App\Services\PlatformService::class)->createTenant(['name'=>'Booking Test Salon','timezone'=>'Africa/Nairobi','business_type_id'=>\App\Models\BusinessType::where('slug','beauty-salon')->value('id'),'plan_id'=>$plan->id,'owner_name'=>'Booking Owner','owner_email'=>'booking-owner@example.test','owner_password'=>'BrowserTestPass123'],$actor->id);
    $user=\App\Models\User::where('email','booking-owner@example.test')->firstOrFail();
    $branch=\Illuminate\Support\Facades\DB::table('branches')->where('tenant_id',$tenant->id)->value('id');
    $base=['tenant_id'=>$tenant->id,'created_at'=>now(),'updated_at'=>now()];
    $db=\Illuminate\Support\Facades\DB::class;
    $stylist=$db::table('salon_staff_profiles')->insertGetId($base+['user_id'=>$user->id,'staff_number'=>'STF-000001','display_name'=>'Asha Stylist','title'=>'Hair Stylist','status'=>'active']);
    $db::table('salon_staff_branch')->insert(['tenant_id'=>$tenant->id,'salon_staff_profile_id'=>$stylist,'branch_id'=>$branch]);
    $client=$db::table('salon_clients')->insertGetId($base+['branch_id'=>$branch,'client_number'=>'CLI-000001','first_name'=>'Amina','last_name'=>'Ali','phone'=>'0700123456','email'=>'amina@example.test','status'=>'active','created_by'=>$user->id,'updated_by'=>$user->id]);
    $category=$db::table('service_categories')->insertGetId($base+['name'=>'Hair','status'=>'active']);
    $services=[];
    foreach (['Haircut'=>[30,15], 'Color'=>[60,30]] as $name=>[$duration,$price]) {
        $id=$db::table('salon_services')->insertGetId($base+['name'=>$name,'service_category_id'=>$category,'duration_minutes'=>$duration,'price'=>$price,'status'=>'active','requires_deposit'=>false]);
        $db::table('salon_service_branch')->insert(['tenant_id'=>$tenant->id,'salon_service_id'=>$id,'branch_id'=>$branch]);
        $db::table('salon_service_staff')->insert(['tenant_id'=>$tenant->id,'service_id'=>$id,'salon_staff_profile_id'=>$stylist]); $services[]=$id;
    }
    foreach(range(1,7) as $day) {
        $hours=$base+['branch_id'=>$branch,'day_of_week'=>$day,'is_available'=>true,'start_time'=>'00:00','end_time'=>'23:59'];
        $db::table('salon_location_hours')->insert($hours); $db::table('salon_staff_schedules')->insert($hours+['stylist_id'=>$stylist]);
    }
    return ['tenant'=>$tenant->id,'user'=>$user->id,'branch'=>$branch,'stylist'=>$stylist,'client'=>$client,'services'=>$services];
}

<?php
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php'; $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$database=config('database.connections.sqlite.database');
if(config('database.default')!=='sqlite'||!str_starts_with(str_replace('\\','/',$database),str_replace('\\','/',storage_path('framework/testing/salon-booking-')))) throw new RuntimeException('Dedicated concurrency database required.');
if($argv[1]==='setup') {
    \Illuminate\Support\Facades\Artisan::call('migrate',['--force'=>true]);
    require __DIR__.'/salon-booking-fixture.php'; $fixture=salonBookingFixture();
    file_put_contents($database.'.fixture',json_encode($fixture)); exit;
}
$fixture=json_decode(file_get_contents($database.'.fixture'),true); $worker=$argv[1];
$user=\App\Models\User::findOrFail($fixture['user']); request()->setUserResolver(fn()=>$user);
app(\App\Tenancy\TenantContext::class)->set($fixture['tenant']);
$tenant=\App\Models\Tenant::findOrFail($fixture['tenant']);
$c=['clinic'=>$tenant,'permissions'=>['*'],'branches'=>\Illuminate\Support\Facades\DB::table('branches')->where('tenant_id',$tenant->id)->get(),'limits'=>[]];
touch($database.'.ready'.$worker); $deadline=microtime(true)+15;
while(!file_exists($database.'.go')) { if(microtime(true)>$deadline)throw new RuntimeException('Worker barrier timed out.'); usleep(20000); }
try {
    \Illuminate\Support\Facades\DB::transaction(function()use($c,$fixture){
        app(\App\Services\BookingCore::class)->lock($fixture['tenant']); usleep(400000);
        app(\App\Services\SalonBookingService::class)->save($c,['branch_id'=>$fixture['branch'],'client_id'=>$fixture['client'],'stylist_id'=>$fixture['stylist'],'service_ids'=>[$fixture['services'][0]],'date'=>now($c['clinic']->timezone)->addDay()->toDateString(),'start_time'=>'10:00','source'=>'reception']);
    },5);
    echo 'CREATED';
}catch(\Illuminate\Validation\ValidationException $e){
    if(!str_contains(json_encode($e->errors()),'overlapping'))throw $e;
    echo 'CONFLICT';
}

<?php
use App\Models\{BusinessType, Plan, User};
use App\Services\PlatformService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || str_replace('\\', '/', config('database.connections.sqlite.database')) !== str_replace('\\', '/', storage_path('framework/testing/saas-browser.sqlite'))) throw new RuntimeException('Dedicated browser database required.');
(new Database\Seeders\BusinessTypeSeeder)->run();
$actor = User::where('email', 'browser-admin@example.test')->firstOrFail();
$plan = Plan::create(['name'=>'Business Test', 'branch_limit'=>2, 'member_limit'=>10, 'trial_days'=>14, 'features'=>array_fill_keys(['patient_management','appointments','clinicians','emr','prescriptions','pharmacy','billing','basic_reports'],true)]);
$owner = null;
foreach (['clinic','beauty-salon','stadium','dental'] as $slug) {
    $tenant = app(PlatformService::class)->createTenant(['name'=>'Phase '.$slug, 'slug'=>'phase-'.$slug, 'business_type_id'=>BusinessType::where('slug',$slug)->value('id'), 'timezone'=>'Africa/Nairobi', 'plan_id'=>$plan->id, 'owner_name'=>'Business Owner', 'owner_email'=>'phase-'.$slug.'@example.test', 'owner_password'=>'BrowserTestPass123'], $actor->id);
    if (!$owner) $owner = User::where('email','phase-clinic@example.test')->firstOrFail();
    else DB::table('tenant_memberships')->insert(['tenant_id'=>$tenant->id,'user_id'=>$owner->id,'role'=>'owner','status'=>'active','all_branches'=>true]);
}

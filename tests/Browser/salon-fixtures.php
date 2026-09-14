<?php
use App\Models\{BusinessType,Plan,User};
use App\Services\PlatformService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
require __DIR__.'/../../vendor/autoload.php';$app=require __DIR__.'/../../bootstrap/app.php';$app->make(Kernel::class)->bootstrap();
if(config('database.default')!=='sqlite'||str_replace('\\','/',config('database.connections.sqlite.database'))!==str_replace('\\','/',storage_path('framework/testing/saas-browser.sqlite')))throw new RuntimeException('Dedicated browser database required.');
(new Database\Seeders\BusinessTypeSeeder)->run();
$actor=User::where('email','browser-admin@example.test')->firstOrFail();
$plan=Plan::create(['name'=>'Salon Core Pro','branch_limit'=>3,'member_limit'=>10,'trial_days'=>14,'features'=>['clients'=>true,'salon_staff'=>true,'services'=>true,'multi_branch'=>true]]);
$tenant=app(PlatformService::class)->createTenant(['name'=>'Core Salon','slug'=>'core-salon','business_type_id'=>BusinessType::where('slug','beauty-salon')->value('id'),'timezone'=>'Africa/Nairobi','plan_id'=>$plan->id,'owner_name'=>'Core Owner','owner_email'=>'core-owner@example.test','owner_password'=>'BrowserTestPass123'],$actor->id);
$user=User::factory()->create(['name'=>'Second Stylist']);DB::table('tenant_memberships')->insert(['tenant_id'=>$tenant->id,'user_id'=>$user->id,'role'=>'staff','status'=>'active','all_branches'=>true]);
DB::table('branches')->insert(['tenant_id'=>$tenant->id,'name'=>'Second Salon Location','status'=>'active']);

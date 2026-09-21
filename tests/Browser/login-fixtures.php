<?php

use App\Models\{BusinessType, Plan, User};
use App\Services\PlatformService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || str_replace('\\', '/', config('database.connections.sqlite.database')) !== str_replace('\\', '/', storage_path('framework/testing/saas-browser.sqlite'))) {
    throw new RuntimeException('Dedicated browser database required.');
}
(new Database\Seeders\BusinessTypeSeeder)->run();
if (User::where('email', 'login-demo@example.test')->exists()) return;
$plan = Plan::create(['name' => 'Pro', 'status' => 'active', 'branch_limit' => 2, 'member_limit' => 10, 'trial_days' => 14,
    'features' => array_fill_keys(['patient_management', 'appointments', 'clinicians', 'emr', 'prescriptions', 'pharmacy', 'billing', 'basic_reports'], true)]);
app(PlatformService::class)->createTenant([
    'name' => 'Afriso Demo', 'slug' => 'afriso-login-demo', 'business_type_id' => BusinessType::where('slug', 'clinic')->value('id'),
    'timezone' => 'Africa/Nairobi', 'plan_id' => $plan->id, 'owner_name' => 'Demo Owner',
    'owner_email' => 'login-demo@example.test', 'owner_password' => 'BrowserTestPass123',
], User::where('email', 'browser-admin@example.test')->value('id'));
User::create(['name' => 'Unverified Owner', 'email' => 'login-unverified@example.test', 'password' => 'BrowserTestPass123']);

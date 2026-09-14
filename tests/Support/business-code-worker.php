<?php

use App\Models\{BusinessType, Plan, User};
use App\Services\PlatformService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\{Artisan, DB};

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$database = config('database.connections.sqlite.database');
if (config('database.default') !== 'sqlite' || !str_starts_with(str_replace('\\', '/', $database), str_replace('\\', '/', storage_path('framework/testing/business-code-')))) {
    throw new RuntimeException('Concurrency tests require their dedicated temporary database.');
}
if ($argv[1] === 'setup') {
    Artisan::call('migrate', ['--force' => true]);
    User::factory()->create(['email' => 'actor@example.test']);
    Plan::create(['name' => 'Concurrent', 'branch_limit' => 2, 'member_limit' => 5, 'trial_days' => 14]);
    exit;
}
$worker = $argv[1];
touch($database.'.ready'.$worker);
$deadline = microtime(true) + 15;
while (!file_exists($database.'.go')) {
    if (microtime(true) > $deadline) throw new RuntimeException('Timed out waiting for concurrent workers.');
    usleep(20000);
}
$actor = User::where('email', 'actor@example.test')->firstOrFail();
$plan = Plan::where('name', 'Concurrent')->firstOrFail();
$type = BusinessType::where('slug', 'clinic')->firstOrFail();
// Hold the winning lock briefly so the second process must wait for it.
$tenant = DB::transaction(function () use ($worker, $actor, $plan, $type) {
    app(\App\Services\BusinessCodeGenerator::class)->lockSequence();
    usleep(400000);
    return app(PlatformService::class)->createTenant(['name' => 'Concurrent '.$worker, 'timezone' => 'Africa/Nairobi',
        'business_type_id' => $type->id, 'plan_id' => $plan->id, 'owner_name' => 'Owner',
        'owner_email' => 'worker'.$worker.'@example.test', 'owner_password' => 'SecurePass12345'], $actor->id);
}, 5);
echo $tenant->slug;

<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$database = config('database.connections.sqlite.database');
if (config('database.default') !== 'sqlite' || !str_starts_with(str_replace('\\', '/', $database), str_replace('\\', '/', storage_path('framework/testing/shared-billing-')))) {
    throw new RuntimeException('Dedicated billing concurrency database required.');
}
if ($argv[1] === 'setup') {
    \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    require __DIR__.'/clinic-billing-fixture.php';
    file_put_contents($database.'.fixture', json_encode(clinicBillingFixture()));
    exit;
}
$fixture = json_decode(file_get_contents($database.'.fixture'), true);
$user = \App\Models\User::findOrFail($fixture['user']); request()->setUserResolver(fn () => $user);
app(\App\Tenancy\TenantContext::class)->set($fixture['tenant']);
$tenant = \App\Models\Tenant::findOrFail($fixture['tenant']);
$context = ['clinic' => $tenant, 'business_type' => ['slug' => 'clinic'], 'business_modules' => ['clinical' => true], 'permissions' => ['*'],
    'branches' => \Illuminate\Support\Facades\DB::table('branches')->where('tenant_id', $tenant->id)->get(), 'limits' => ['invoice_limit' => 1]];
touch($database.'.ready'.$argv[1]);
$deadline = microtime(true) + 20;
while (!file_exists($database.'.go')) {
    if (microtime(true) > $deadline) throw new RuntimeException('Workers failed to synchronize.');
    usleep(20000);
}
echo app(\App\Services\BillingService::class)->createFromSource($context, 'clinic_appointment', $fixture['appointment'])->id;

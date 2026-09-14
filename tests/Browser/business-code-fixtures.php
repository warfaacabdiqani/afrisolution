<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || str_replace('\\', '/', config('database.connections.sqlite.database')) !== str_replace('\\', '/', storage_path('framework/testing/saas-browser.sqlite'))) {
    throw new RuntimeException('Browser fixtures require the dedicated browser database.');
}
(new \Database\Seeders\BusinessTypeSeeder)->run();
\App\Models\Plan::create(['name' => 'Business code plan', 'slug' => 'business-code-plan', 'branch_limit' => 2, 'member_limit' => 5, 'trial_days' => 14]);

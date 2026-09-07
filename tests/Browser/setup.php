<?php

use App\Models\User;
use App\Models\PlatformRole;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$expected = storage_path('framework/testing/saas-browser.sqlite');
if (config('database.default') !== 'sqlite' || str_replace('\\', '/', config('database.connections.sqlite.database')) !== str_replace('\\', '/', $expected)) {
    throw new RuntimeException('Browser fixtures require the dedicated browser SQLite database.');
}
if (! is_dir(dirname($expected))) {
    mkdir(dirname($expected), 0777, true);
}
if (! file_exists($expected)) {
    touch($expected);
}
Artisan::call('migrate:fresh', ['--force' => true]);
$user = new User(['name' => 'Browser Admin', 'email' => 'browser-admin@example.test', 'password' => 'BrowserTestPass123']);
$user->is_platform_admin = true;
$user->save();
$user->platformRoles()->sync([PlatformRole::where('slug', 'super-administrator')->value('id')]);

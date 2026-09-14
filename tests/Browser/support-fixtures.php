<?php

use App\Models\BusinessType;
use App\Models\Plan;
use App\Models\PlatformRole;
use App\Models\User;
use App\Services\PlatformService;
use App\Services\SupportTicketService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$expected = storage_path('framework/testing/saas-browser.sqlite');
if (config('database.default') !== 'sqlite' || str_replace('\\', '/', config('database.connections.sqlite.database')) !== str_replace('\\', '/', $expected)) {
    throw new RuntimeException('Support fixtures require the dedicated browser SQLite database.');
}
$admin = User::where('email', 'browser-admin@example.test')->firstOrFail();
$plan = Plan::create(['name' => 'Support browser plan', 'branch_limit' => 2, 'member_limit' => 5, 'trial_days' => 14]);
$fixtures = [];
foreach (['support-clinic' => 'clinic', 'support-salon' => 'beauty-salon'] as $slug => $type) {
    $businessType = BusinessType::firstOrCreate(['slug' => $type], ['name' => 'Beauty Salon', 'category' => 'Services', 'status' => 'active']);
    $tenant = app(PlatformService::class)->createTenant([
        'name' => ucwords(str_replace('-', ' ', $slug)), 'slug' => $slug, 'business_type_id' => $businessType->id,
        'timezone' => 'Africa/Nairobi', 'plan_id' => $plan->id, 'owner_name' => 'Owner '.$slug,
        'owner_email' => $slug.'@example.test', 'owner_password' => 'BrowserTestPass123',
    ], $admin->id);
    $owner = User::where('email', $slug.'@example.test')->firstOrFail();
    $ticket = app(SupportTicketService::class)->create($tenant->id, DB::table('branches')->where('tenant_id', $tenant->id)->value('id'), $owner, [
        'subject' => $slug === 'support-clinic' ? 'Cannot print an invoice' : 'Booking calendar question',
        'category' => 'Technical Issue', 'priority' => 'Normal', 'description' => 'Please help us with this request.',
        'current_page' => '/app/billing', 'steps_to_reproduce' => 'Open invoice and click print.',
        'expected_result' => 'A printable invoice', 'actual_result' => 'Nothing happens',
    ], null);
    $fixtures[$slug] = $ticket + ['tenant_id' => $tenant->id, 'business_type_id' => $businessType->id];
}
$reader = new User(['name' => 'Support Reader', 'email' => 'support-reader@example.test', 'password' => 'BrowserTestPass123']);
$reader->is_platform_admin = true; $reader->save();
$role = PlatformRole::create(['name' => 'Support Reader', 'slug' => 'support-reader']);
$role->permissions()->sync(DB::table('platform_permissions')->where('name', 'support_tickets.view')->pluck('id'));
$reader->platformRoles()->sync([$role->id]);
echo json_encode($fixtures);

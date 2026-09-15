<?php
require __DIR__.'/../../vendor/autoload.php';
$app=require __DIR__.'/../../bootstrap/app.php'; $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if(config('database.default')!=='sqlite'||str_replace('\\','/',config('database.connections.sqlite.database'))!==str_replace('\\','/',storage_path('framework/testing/saas-browser.sqlite'))) throw new RuntimeException('Dedicated browser database required.');
require __DIR__.'/../Support/salon-booking-fixture.php';
salonBookingFixture();

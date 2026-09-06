<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClinicController;
use App\Http\Controllers\PlatformController;
use App\Http\Controllers\ProvisioningController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::get('session', [AuthController::class, 'session']);
    Route::post('session/clinic', [AuthController::class, 'select']);
    Route::prefix('platform')->middleware('platform')->group(function () {
        Route::get('dashboard', [PlatformController::class, 'dashboard']);
        Route::get('tenants', [PlatformController::class, 'tenants']);
        Route::post('tenants', [PlatformController::class, 'store']);
        Route::get('tenants/{tenant}', [PlatformController::class, 'show']);
        Route::patch('tenants/{tenant}', [PlatformController::class, 'update']);
        Route::get('tenants/{tenant}/audits', [PlatformController::class, 'tenantAudits']);
        Route::get('tenants/{tenant}/subscription', [PlatformController::class, 'subscription']);
        Route::put('tenants/{tenant}/subscription', [PlatformController::class, 'updateSubscription']);
        Route::get('plans', [PlatformController::class, 'plans']);
        Route::post('plans', [PlatformController::class, 'storePlan']);
        Route::get('audits', [PlatformController::class, 'audits']);
        Route::get('tenants/{tenant}/members', [ProvisioningController::class, 'members']);
        Route::post('tenants/{tenant}/members', [ProvisioningController::class, 'addMember']);
        Route::patch('tenants/{tenant}/members/{member}', [ProvisioningController::class, 'updateMember'])->whereNumber('member');
        Route::get('tenants/{tenant}/branches', [ProvisioningController::class, 'branches']);
        Route::post('tenants/{tenant}/branches', [ProvisioningController::class, 'addBranch']);
    });
    Route::prefix('clinic')->middleware('tenant')->group(function () {
        Route::get('branches', [ClinicController::class, 'branches']);
        Route::get('branches/{branch}', [ClinicController::class, 'branch'])->whereNumber('branch');
    });
});

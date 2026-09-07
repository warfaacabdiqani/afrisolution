<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClinicController;
use App\Http\Controllers\PlatformController;
use App\Http\Controllers\PlatformRoleController;
use App\Http\Controllers\PlatformUserController;
use App\Http\Controllers\ProvisioningController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('v1')->group(function () {
    Route::get('session', [AuthController::class, 'session']);
    Route::post('session/clinic', [AuthController::class, 'select']);
    Route::prefix('platform')->middleware('platform')->group(function () {
        Route::get('dashboard', [PlatformController::class, 'dashboard'])->middleware('platform.permission:platform.dashboard.view');
        Route::get('subscriptions', [PlatformController::class, 'subscriptions'])->middleware('platform.permission:subscriptions.view');
        Route::get('users', [PlatformUserController::class, 'index']);
        Route::post('users', [PlatformUserController::class, 'store']);
        Route::get('users/{user}', [PlatformUserController::class, 'show']);
        Route::put('users/{user}', [PlatformUserController::class, 'update']);
        Route::get('roles', [PlatformUserController::class, 'roles']);
        Route::post('roles', [PlatformRoleController::class, 'store']);
        Route::put('roles/{role}', [PlatformRoleController::class, 'update']);
        Route::get('permissions', [PlatformRoleController::class, 'permissions']);
        Route::get('tenants', [PlatformController::class, 'tenants'])->middleware('platform.permission:tenants.view');
        Route::post('tenants', [PlatformController::class, 'store'])->middleware('platform.permission:tenants.manage');
        Route::get('tenants/{tenant}', [PlatformController::class, 'show'])->middleware('platform.permission:tenants.view');
        Route::patch('tenants/{tenant}', [PlatformController::class, 'update'])->middleware('platform.permission:tenants.manage');
        Route::get('tenants/{tenant}/audits', [PlatformController::class, 'tenantAudits'])->middleware('platform.permission:tenants.view');
        Route::get('tenants/{tenant}/subscription', [PlatformController::class, 'subscription'])->middleware('platform.permission:subscriptions.view');
        Route::put('tenants/{tenant}/subscription', [PlatformController::class, 'updateSubscription'])->middleware('platform.permission:subscriptions.manage');
        Route::get('plans', [PlatformController::class, 'plans'])->middleware('platform.permission:plans.view');
        Route::post('plans', [PlatformController::class, 'storePlan'])->middleware('platform.permission:plans.manage');
        Route::get('plans/{plan}', [PlatformController::class, 'showPlan'])->middleware('platform.permission:plans.view');
        Route::put('plans/{plan}', [PlatformController::class, 'updatePlan'])->middleware('platform.permission:plans.manage');
        Route::delete('plans/{plan}', [PlatformController::class, 'destroyPlan'])->middleware('platform.permission:plans.manage');
        Route::get('plans/{plan}/subscriptions', [PlatformController::class, 'planSubscriptions'])->middleware('platform.permission:plans.view');
        Route::get('plans/{plan}/audits', [PlatformController::class, 'planAudits'])->middleware('platform.permission:plans.view');
        Route::get('audits', [PlatformController::class, 'audits'])->middleware('platform.permission:audit.view');
        Route::get('tenants/{tenant}/members', [ProvisioningController::class, 'members'])->middleware('platform.permission:tenants.view');
        Route::post('tenants/{tenant}/members', [ProvisioningController::class, 'addMember'])->middleware('platform.permission:tenants.manage');
        Route::patch('tenants/{tenant}/members/{member}', [ProvisioningController::class, 'updateMember'])->whereNumber('member')->middleware('platform.permission:tenants.manage');
        Route::get('tenants/{tenant}/branches', [ProvisioningController::class, 'branches'])->middleware('platform.permission:tenants.view');
        Route::post('tenants/{tenant}/branches', [ProvisioningController::class, 'addBranch'])->middleware('platform.permission:tenants.manage');
    });
    Route::prefix('clinic')->middleware('tenant')->group(function () {
        Route::get('branches', [ClinicController::class, 'branches']);
        Route::get('branches/{branch}', [ClinicController::class, 'branch'])->whereNumber('branch');
    });
});

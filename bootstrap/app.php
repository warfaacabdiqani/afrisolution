<?php

use App\Http\Middleware\PlatformAdmin;
use App\Http\Middleware\PlatformPermission;
use App\Http\Middleware\CheckPlatformAvailability;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->redirectGuestsTo('/app/login');
        $middleware->alias([
            'platform' => PlatformAdmin::class,
            'platform.permission' => PlatformPermission::class,
            'platform.available' => CheckPlatformAvailability::class,
            'tenant' => ResolveTenant::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['owner_password', 'owner_password_confirmation']);
    })->create();

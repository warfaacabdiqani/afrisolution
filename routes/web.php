<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/register', [\App\Http\Controllers\RegistrationController::class, 'store'])->middleware('throttle:login');
Route::post('/register/validate', [\App\Http\Controllers\RegistrationController::class, 'validateStep'])->middleware('throttle:30,1');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/email/verify/{id}/{hash}', [\App\Http\Controllers\EmailVerificationController::class, 'verify'])
    ->middleware(['signed:relative', 'throttle:6,1'])->whereNumber('id')->name('verification.verify');

Route::view('/', 'app');

// Keep SPA navigation under /app so API and infrastructure routes retain
// their normal responses, including JSON 404s for unknown API endpoints.
Route::view('/app/{path?}', 'app')->where('path', '.*');

// Audit logs are read-only; unsupported mutations are absent resources.
Route::match(['PUT', 'DELETE'], '/api/v1/platform/audits/{id}', fn () => abort(404))->whereNumber('id');

// Fallback to the SPA for any non-API route so the Vue router can render the
// custom NotFoundView for unknown pages instead of Laravel's default page.
Route::fallback(fn (\Illuminate\Http\Request $request) => $request->is('api/*') ? abort(404) : view('app'));

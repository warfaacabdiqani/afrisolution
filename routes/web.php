<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');

Route::view('/', 'app');

// Keep SPA navigation under /app so API and infrastructure routes retain
// their normal responses, including JSON 404s for unknown API endpoints.
Route::view('/app/{path?}', 'app')->where('path', '.*');

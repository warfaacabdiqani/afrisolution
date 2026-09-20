<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureEmailVerifiedForApi
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->is('api/v1/public/*', 'api/v1/session', 'api/v1/email/verification/resend')) return $next($request);
        if ($request->user() && !$request->user()->hasVerifiedEmail()) {
            return response()->json(['code' => 'EMAIL_VERIFICATION_REQUIRED', 'message' => 'Verify your email address to continue.'], 403)
                ->header('Cache-Control', 'no-store');
        }
        return $next($request);
    }
}

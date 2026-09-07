<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class PlatformPermission
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        abort_unless($request->user()?->hasPlatformPermission($permission), 403);
        return $next($request);
    }
}

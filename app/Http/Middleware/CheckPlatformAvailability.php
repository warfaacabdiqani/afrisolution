<?php

namespace App\Http\Middleware;

use App\Services\SystemSettingsService;
use Closure;
use Illuminate\Http\Request;

class CheckPlatformAvailability
{
    public function handle(Request $request, Closure $next)
    {
        $settings = app(SystemSettingsService::class);
        if ($settings->get('general.platform_status','active') === 'maintenance') {
            return response()->json(['message'=>$settings->get('general.maintenance_message','The platform is undergoing scheduled maintenance. Please try again shortly.')],503);
        }
        return $next($request);
    }
}

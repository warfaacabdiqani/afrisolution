<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResolveTenant
{
    public function handle(Request $request, Closure $next)
    {
        $context = app(TenantContext::class);
        $context->clear();
        try {
            $id = $request->session()->get('tenant_id');
            // A stale browser tab may detect a switch, but cannot choose ownership.
            if ($request->hasHeader('X-Clinic-Context')) {
                abort_unless((string) $request->header('X-Clinic-Context') === (string) $id, 409, 'The active clinic changed in another tab. Refresh this page.');
            }
            $allowed = DB::table('tenant_memberships')->join('tenants', 'tenants.id', '=', 'tenant_memberships.tenant_id')
                ->where('tenant_memberships.user_id', $request->user()->id)->where('tenant_memberships.tenant_id', $id)
                ->where('tenant_memberships.status', 'active')->where('tenants.status', 'active')->exists();
            abort_unless($allowed, 403, 'Select an active clinic you belong to.');
            $context->set((int) $id);
            $subscription = DB::table('subscriptions')->where('tenant_id', $id)->first();
            if (!($subscription && ($subscription->status === 'active' || ($subscription->status === 'trial' && $subscription->trial_ends_at && now()->lt($subscription->trial_ends_at))))) app(\App\Services\ClinicAccessService::class)->deny('SUBSCRIPTION_INACTIVE', 'The business subscription is inactive or its trial has ended.');

            return $next($request);
        } finally {
            $context->clear();
        }
    }
}

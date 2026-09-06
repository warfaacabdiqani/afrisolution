<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlanRequest;
use App\Http\Requests\SubscriptionRequest;
use App\Http\Requests\TenantRequest;
use App\Http\Resources\AuditResource;
use App\Http\Resources\PlanResource;
use App\Http\Resources\PlatformDashboardResource;
use App\Http\Resources\SubscriptionResource;
use App\Http\Resources\TenantResource;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\PlatformService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PlatformController extends Controller
{
    public function dashboard()
    {
        Gate::authorize('viewAny', Tenant::class);

        $now = now();
        $soon = $now->copy()->addDays(14);

        $stats = [
            'total_clinics' => DB::table('tenants')->count(),
            'active_clinics' => DB::table('tenants')->where('status', 'active')->count(),
            'suspended_clinics' => DB::table('tenants')->where('status', 'suspended')->count(),
            'trial_clinics' => DB::table('subscriptions')->where('status', 'trial')->where('trial_ends_at', '>', $now)->count(),
            'active_subscriptions' => DB::table('subscriptions')->where('status', 'active')->count(),
            'total_members' => DB::table('tenant_memberships')->where('status', 'active')->count(),
        ];

        $expiringTrials = DB::table('subscriptions')
            ->join('tenants', 'tenants.id', '=', 'subscriptions.tenant_id')
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->where('subscriptions.status', 'trial')
            ->whereBetween('subscriptions.trial_ends_at', [$now, $soon])
            ->orderBy('subscriptions.trial_ends_at')
            ->limit(6)
            ->get(['tenants.id', 'tenants.name', 'plans.name as plan_name', 'subscriptions.trial_ends_at']);

        $recentActivity = DB::table('platform_audit_logs')
            ->leftJoin('users', 'users.id', '=', 'platform_audit_logs.actor_id')
            ->latest('platform_audit_logs.id')
            ->limit(8)
            ->get(['platform_audit_logs.id', 'platform_audit_logs.action', 'platform_audit_logs.subject_type', 'platform_audit_logs.subject_id', 'platform_audit_logs.created_at', 'users.name as actor_name']);

        return new PlatformDashboardResource([
            'stats' => $stats,
            'expiring_trials' => $expiringTrials,
            'recent_activity' => $recentActivity,
        ]);
    }

    public function tenants()
    {
        Gate::authorize('viewAny', Tenant::class);

        return TenantResource::collection(Tenant::latest('id')->paginate(20));
    }

    public function store(TenantRequest $request, PlatformService $service)
    {
        Gate::authorize('create', Tenant::class);

        return (new TenantResource($service->createTenant($request->validated(), $request->user()->id)))->response()->setStatusCode(201);
    }

    public function update(TenantRequest $request, Tenant $tenant, PlatformService $service)
    {
        Gate::authorize('update', $tenant);

        return new TenantResource($service->updateTenant($tenant, $request->safe()->only(['name', 'timezone', 'status']), $request->user()->id));
    }

    public function plans()
    {
        Gate::authorize('viewAny', Tenant::class);

        return PlanResource::collection(Plan::orderBy('id')->get());
    }

    public function storePlan(PlanRequest $request, PlatformService $service)
    {
        Gate::authorize('create', Tenant::class);

        return (new PlanResource($service->createPlan($request->validated(), $request->user()->id)))->response()->setStatusCode(201);
    }

    public function subscription(Tenant $tenant)
    {
        Gate::authorize('update', $tenant);

        return new SubscriptionResource(DB::table('subscriptions')->where('tenant_id', $tenant->id)->firstOrFail());
    }

    public function updateSubscription(SubscriptionRequest $request, Tenant $tenant, PlatformService $service)
    {
        Gate::authorize('update', $tenant);

        return new SubscriptionResource($service->updateSubscription($tenant, $request->validated(), $request->user()->id));
    }

    public function audits()
    {
        Gate::authorize('viewAny', Tenant::class);

        return AuditResource::collection(DB::table('platform_audit_logs')->latest('id')->paginate(30));
    }
}

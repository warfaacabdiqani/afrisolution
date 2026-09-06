<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlanRequest;
use App\Http\Requests\SubscriptionRequest;
use App\Http\Requests\TenantRequest;
use App\Http\Resources\AuditResource;
use App\Http\Resources\PlanResource;
use App\Http\Resources\SubscriptionResource;
use App\Http\Resources\TenantResource;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\PlatformService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PlatformController extends Controller
{
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

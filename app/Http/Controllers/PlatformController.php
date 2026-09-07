<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlanRequest;
use App\Http\Requests\PlatformSubscriptionIndexRequest;
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

        $query = $this->tenantSummaryQuery();
        if ($search = request()->string('search')->trim()->toString()) {
            $query->where(fn ($builder) => $builder->where('name', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%"));
        }
        if (in_array(request('status'), ['active', 'suspended'], true)) {
            $query->where('status', request('status'));
        }
        if (request()->filled('plan_id')) {
            $query->whereExists(fn ($builder) => $builder->selectRaw('1')->from('subscriptions')
                ->whereColumn('subscriptions.tenant_id', 'tenants.id')->where('subscriptions.plan_id', request('plan_id')));
        }

        return TenantResource::collection($query->latest('tenants.id')->paginate(20));
    }

    public function show(Tenant $tenant)
    {
        Gate::authorize('update', $tenant);

        return new TenantResource($this->tenantSummaryQuery()->findOrFail($tenant->id));
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
        Gate::authorize('viewAny', Plan::class);

        return PlanResource::collection($this->planSummaryQuery()->orderBy('id')->get());
    }

    public function storePlan(PlanRequest $request, PlatformService $service)
    {
        Gate::authorize('create', Plan::class);

        return (new PlanResource($service->createPlan($request->validated(), $request->user()->id)))->response()->setStatusCode(201);
    }

    public function showPlan(Plan $plan)
    {
        Gate::authorize('view', $plan);

        return new PlanResource($this->planSummaryQuery()->findOrFail($plan->id));
    }

    public function updatePlan(PlanRequest $request, Plan $plan, PlatformService $service)
    {
        Gate::authorize('update', $plan);

        return new PlanResource($service->updatePlan($plan, $request->validated(), $request->user()->id));
    }

    public function destroyPlan(Plan $plan, PlatformService $service)
    {
        Gate::authorize('delete', $plan);
        $service->deletePlan($plan, request()->user()->id);

        return response()->noContent();
    }

    public function planSubscriptions(Plan $plan)
    {
        Gate::authorize('view', $plan);

        return DB::table('subscriptions')
            ->join('tenants', 'tenants.id', '=', 'subscriptions.tenant_id')
            ->where('subscriptions.plan_id', $plan->id)
            ->select([
                'subscriptions.id', 'subscriptions.status', 'subscriptions.created_at', 'subscriptions.trial_ends_at',
                'tenants.id as tenant_id', 'tenants.name as tenant_name',
                DB::raw("(select count(*) from tenant_memberships where tenant_memberships.tenant_id = tenants.id and tenant_memberships.status = 'active') as members_count"),
            ])->latest('subscriptions.id')->paginate(20);
    }

    public function planAudits(Plan $plan)
    {
        Gate::authorize('view', $plan);

        return AuditResource::collection(DB::table('platform_audit_logs')
            ->leftJoin('users', 'users.id', '=', 'platform_audit_logs.actor_id')
            ->where('subject_type', 'plan')->where('subject_id', $plan->id)
            ->select('platform_audit_logs.*', 'users.name as actor_name')->latest('platform_audit_logs.id')->paginate(30));
    }

    public function subscriptions(PlatformSubscriptionIndexRequest $request)
    {
        Gate::authorize('viewAny', Plan::class);
        $filters = $request->validated();
        $query = DB::table('subscriptions')
            ->join('tenants', 'tenants.id', '=', 'subscriptions.tenant_id')
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->select([
                'subscriptions.id', 'subscriptions.status', 'subscriptions.created_at', 'subscriptions.updated_at',
                'subscriptions.trial_ends_at', 'subscriptions.branch_limit', 'subscriptions.member_limit',
                'tenants.id as tenant_id', 'tenants.name as tenant_name', 'tenants.slug as tenant_code',
                'plans.id as plan_id', 'plans.name as plan_name', 'plans.price', 'plans.currency', 'plans.billing_period',
                DB::raw("(select count(*) from tenant_memberships where tenant_memberships.tenant_id = tenants.id and tenant_memberships.status = 'active') as members_count"),
                DB::raw('(select count(*) from branches where branches.tenant_id = tenants.id) as branches_count'),
            ]);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(fn ($builder) => $builder->where('tenants.name', 'like', "%{$search}%")
                ->orWhere('tenants.slug', 'like', "%{$search}%")->orWhere('plans.name', 'like', "%{$search}%"));
        }
        if (! empty($filters['status'])) $query->where('subscriptions.status', $filters['status']);
        if (! empty($filters['plan_id'])) $query->where('subscriptions.plan_id', $filters['plan_id']);

        $paginator = $query->latest('subscriptions.id')->paginate(20);
        $stats = [
            'total' => DB::table('subscriptions')->count(),
            'active' => DB::table('subscriptions')->where('status', 'active')->count(),
            'trial' => DB::table('subscriptions')->where('status', 'trial')->count(),
            'cancelled' => DB::table('subscriptions')->where('status', 'cancelled')->count(),
        ];

        return response()->json([
            'data' => $paginator->items(),
            'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'per_page' => $paginator->perPage(), 'total' => $paginator->total()],
            'stats' => $stats,
        ]);
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

    public function tenantAudits(Tenant $tenant)
    {
        Gate::authorize('update', $tenant);

        return AuditResource::collection(
            DB::table('platform_audit_logs')
                ->where('subject_type', 'tenant')
                ->where('subject_id', $tenant->id)
                ->latest('id')
                ->paginate(30)
        );
    }

    private function tenantSummaryQuery()
    {
        return Tenant::query()->addSelect([
            'owner_name' => DB::table('tenant_memberships')
                ->join('users', 'users.id', '=', 'tenant_memberships.user_id')
                ->select('users.name')->whereColumn('tenant_memberships.tenant_id', 'tenants.id')
                ->where('tenant_memberships.role', 'owner')->where('tenant_memberships.status', 'active')->limit(1),
            'owner_email' => DB::table('tenant_memberships')
                ->join('users', 'users.id', '=', 'tenant_memberships.user_id')
                ->select('users.email')->whereColumn('tenant_memberships.tenant_id', 'tenants.id')
                ->where('tenant_memberships.role', 'owner')->where('tenant_memberships.status', 'active')->limit(1),
            'plan_name' => DB::table('subscriptions')->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
                ->select('plans.name')->whereColumn('subscriptions.tenant_id', 'tenants.id')->limit(1),
            'subscription_status' => DB::table('subscriptions')->select('status')
                ->whereColumn('subscriptions.tenant_id', 'tenants.id')->limit(1),
            'trial_ends_at' => DB::table('subscriptions')->select('trial_ends_at')
                ->whereColumn('subscriptions.tenant_id', 'tenants.id')->limit(1),
            'members_count' => DB::table('tenant_memberships')->selectRaw('count(*)')
                ->whereColumn('tenant_memberships.tenant_id', 'tenants.id')->where('status', 'active'),
            'branches_count' => DB::table('branches')->selectRaw('count(*)')
                ->whereColumn('branches.tenant_id', 'tenants.id'),
        ]);
    }

    private function planSummaryQuery()
    {
        return Plan::query()->addSelect([
            'subscriptions_count' => DB::table('subscriptions')->selectRaw('count(*)')
                ->whereColumn('subscriptions.plan_id', 'plans.id'),
            'active_subscriptions_count' => DB::table('subscriptions')->selectRaw('count(*)')
                ->whereColumn('subscriptions.plan_id', 'plans.id')->where('status', 'active'),
            'trial_subscriptions_count' => DB::table('subscriptions')->selectRaw('count(*)')
                ->whereColumn('subscriptions.plan_id', 'plans.id')->where('status', 'trial'),
        ]);
    }
}

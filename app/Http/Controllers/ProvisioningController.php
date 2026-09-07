<?php

namespace App\Http\Controllers;

use App\Http\Requests\BranchRequest;
use App\Http\Requests\MemberRequest;
use App\Http\Resources\BranchResource;
use App\Http\Resources\MemberResource;
use App\Models\Tenant;
use App\Services\TenantProvisioningService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ProvisioningController extends Controller
{
    public function members(Tenant $tenant)
    {
        Gate::authorize('update', $tenant);

        $assignments = DB::table('branch_memberships')->where('tenant_id', $tenant->id)->get()->groupBy('membership_id');
        $members = DB::table('tenant_memberships')->join('users', 'users.id', '=', 'tenant_memberships.user_id')
            ->where('tenant_id', $tenant->id)->select('tenant_memberships.id', 'users.name', 'users.email', 'tenant_memberships.role', 'tenant_memberships.status', 'tenant_memberships.all_branches', 'tenant_memberships.permissions')->orderBy('tenant_memberships.id')->get();
        foreach ($members as $member) $member->branch_ids = ($assignments->get($member->id) ?? collect())->pluck('branch_id')->all();
        return MemberResource::collection($members);
    }

    public function addMember(MemberRequest $request, Tenant $tenant, TenantProvisioningService $service)
    {
        Gate::authorize('update', $tenant);
        $service->addMember($tenant, $request->validated(), $request->user()->id);

        return response()->noContent(201);
    }

    public function updateMember(MemberRequest $request, Tenant $tenant, int $member, TenantProvisioningService $service)
    {
        Gate::authorize('update', $tenant);
        $service->updateMember($tenant, $member, $request->validated(), $request->user()->id);

        return response()->noContent();
    }

    public function branches(Tenant $tenant)
    {
        Gate::authorize('update', $tenant);

        return BranchResource::collection(DB::table('branches')->where('tenant_id', $tenant->id)->orderBy('id')->get());
    }

    public function addBranch(BranchRequest $request, Tenant $tenant, TenantProvisioningService $service)
    {
        Gate::authorize('update', $tenant);

        return (new BranchResource($service->addBranch($tenant, $request->validated(), $request->user()->id)))->response()->setStatusCode(201);
    }
}

<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TenantProvisioningService
{
    private function subscription(Tenant $tenant): object
    {
        Tenant::lockForUpdate()->findOrFail($tenant->id);

        return DB::table('subscriptions')->where('tenant_id', $tenant->id)->firstOrFail();
    }

    public function addBranch(Tenant $tenant, array $data, int $actor): object
    {
        return DB::transaction(function () use ($tenant, $data, $actor) {
            $sub = $this->subscription($tenant);
            if (DB::table('branches')->where('tenant_id', $tenant->id)->count() >= $sub->branch_limit) {
                throw ValidationException::withMessages(['name' => 'The branch limit has been reached.']);
            }
            $id = DB::table('branches')->insertGetId(['tenant_id' => $tenant->id, 'name' => $data['name'], 'created_at' => now(), 'updated_at' => now()]);
            app(PlatformService::class)->audit($actor, 'branch.created', 'tenant', $tenant->id);

            return DB::table('branches')->where('tenant_id', $tenant->id)->where('id', $id)->first();
        });
    }

    public function addMember(Tenant $tenant, array $data, int $actor): void
    {
        DB::transaction(function () use ($tenant, $data, $actor) {
            $sub = $this->subscription($tenant);
            if (DB::table('tenant_memberships')->where('tenant_id', $tenant->id)->where('status', 'active')->count() >= $sub->member_limit) {
                throw ValidationException::withMessages(['email' => 'The member limit has been reached.']);
            }
            $user = User::create(collect($data)->only(['name', 'email', 'password'])->all());
            DB::table('tenant_memberships')->insert(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'role' => $data['role'], 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            app(PlatformService::class)->audit($actor, 'member.created', 'tenant', $tenant->id);
        });
    }

    public function updateMember(Tenant $tenant, int $id, array $data, int $actor): void
    {
        DB::transaction(function () use ($tenant, $id, $data, $actor) {
            $sub = $this->subscription($tenant);
            $query = DB::table('tenant_memberships')->where('tenant_id', $tenant->id);
            $member = (clone $query)->where('id', $id)->firstOrFail();
            if ($member->role === 'owner' && $member->status === 'active' && ($data['role'] !== 'owner' || $data['status'] !== 'active') &&
             (clone $query)->where('role', 'owner')->where('status', 'active')->count() <= 1) {
                throw ValidationException::withMessages(['role' => 'Keep at least one active clinic owner.']);
            }
            if ($member->status !== 'active' && $data['status'] === 'active' && (clone $query)->where('status', 'active')->count() >= $sub->member_limit) {
                throw ValidationException::withMessages(['status' => 'The member limit has been reached.']);
            }
            (clone $query)->where('id', $id)->update(['role' => $data['role'], 'status' => $data['status'], 'updated_at' => now()]);
            app(PlatformService::class)->audit($actor, 'member.updated', 'tenant', $tenant->id);
        });
    }
}

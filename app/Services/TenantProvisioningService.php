<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TenantProvisioningService
{
    private function saveAccess(Tenant $tenant, int $membership, array $data): void
    {
        $changes = [];
        if (array_key_exists('permissions', $data)) $changes['permissions'] = $data['permissions'] === null ? null : json_encode($data['permissions']);
        if (array_key_exists('all_branches', $data)) $changes['all_branches'] = $data['all_branches'];
        if ($changes) DB::table('tenant_memberships')->where('tenant_id', $tenant->id)->where('id', $membership)->update($changes);
        if (array_key_exists('branch_ids', $data)) {
            DB::table('branch_memberships')->where('tenant_id', $tenant->id)->where('membership_id', $membership)->delete();
            foreach ($data['branch_ids'] as $branch) {
                abort_unless(DB::table('branches')->where('tenant_id', $tenant->id)->where('id', $branch)->exists(), 422, 'The selected branch does not belong to this clinic.');
                DB::table('branch_memberships')->insert(['tenant_id' => $tenant->id, 'membership_id' => $membership, 'branch_id' => $branch]);
            }
        }
    }
    private function enforceDoctorLimit(Tenant $tenant, object $subscription): void
    {
        $limit = DB::table('plans')->where('id', $subscription->plan_id)->value('doctor_limit');
        if ($limit !== null && DB::table('tenant_memberships')->where('tenant_id', $tenant->id)->where('status', 'active')->where('role', 'doctor')->count() >= $limit) {
            throw ValidationException::withMessages(['role' => "Your current plan allows up to {$limit} doctors. Upgrade your subscription to add more doctors."]);
        }
    }
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
            if ($data['role'] === 'doctor') $this->enforceDoctorLimit($tenant, $sub);
            $user = User::create(collect($data)->only(['name', 'email', 'password'])->all());
            $membership = DB::table('tenant_memberships')->insertGetId(['tenant_id' => $tenant->id, 'user_id' => $user->id, 'role' => $data['role'], 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            $this->saveAccess($tenant, $membership, $data);
            app(PlatformService::class)->audit($actor, 'member.created', 'tenant', $tenant->id);
        });
    }

    public function updateMember(Tenant $tenant, int $id, array $data, int $actor): void
    {
        DB::transaction(function () use ($tenant, $id, $data, $actor) {
            $sub = $this->subscription($tenant);
            $query = DB::table('tenant_memberships')->where('tenant_id', $tenant->id);
            $member = (clone $query)->where('id', $id)->firstOrFail();
            if ($data['role'] === 'doctor' && $data['status'] === 'active' && ($member->role !== 'doctor' || $member->status !== 'active')) $this->enforceDoctorLimit($tenant, $sub);
            if ($member->role === 'owner' && $member->status === 'active' && ($data['role'] !== 'owner' || $data['status'] !== 'active') &&
             (clone $query)->where('role', 'owner')->where('status', 'active')->count() <= 1) {
                throw ValidationException::withMessages(['role' => 'Keep at least one active clinic owner.']);
            }
            if ($member->status !== 'active' && $data['status'] === 'active' && (clone $query)->where('status', 'active')->count() >= $sub->member_limit) {
                throw ValidationException::withMessages(['status' => 'The member limit has been reached.']);
            }
            (clone $query)->where('id', $id)->update(['role' => $data['role'], 'status' => $data['status'], 'updated_at' => now()]);
            $this->saveAccess($tenant, $id, $data);
            app(PlatformService::class)->audit($actor, 'member.updated', 'tenant', $tenant->id);
        });
    }
}

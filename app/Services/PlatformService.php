<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\PlatformPermission;
use App\Models\PlatformRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlatformService
{
    public function audit(int $actor, string $action, string $type, int $id, array $metadata = []): void
    {
        DB::table('platform_audit_logs')->insert(['actor_id' => $actor, 'action' => $action, 'subject_type' => $type, 'subject_id' => $id, 'metadata' => $metadata ? json_encode($metadata) : null, 'created_at' => now()]);
    }

    public function createTenant(array $data, int $actor): Tenant
    {
        return DB::transaction(function () use ($data, $actor) {
            $plan = Plan::findOrFail($data['plan_id']);
            $tenant = Tenant::create(collect($data)->only(['name', 'slug', 'timezone'])->all());
            $owner = User::create(['name' => $data['owner_name'], 'email' => $data['owner_email'], 'password' => $data['owner_password']]);
            // Explicit platform provisioning boundary: all ownership is assigned from the newly created tenant.
            DB::table('tenant_memberships')->insert(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'role' => 'owner', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('branches')->insert(['tenant_id' => $tenant->id, 'name' => 'Main branch', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('subscriptions')->insert(['tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'status' => 'trial', 'trial_ends_at' => now()->addDays($plan->trial_days), 'branch_limit' => $plan->branch_limit, 'member_limit' => $plan->member_limit, 'created_at' => now(), 'updated_at' => now()]);
            $this->audit($actor, 'tenant.created', 'tenant', $tenant->id);

            return $tenant;
        });
    }

    public function updateTenant(Tenant $tenant, array $data, int $actor): Tenant
    {
        return DB::transaction(function () use ($tenant, $data, $actor) {
            $tenant = Tenant::lockForUpdate()->findOrFail($tenant->id);
            $tenant->update($data);
            $this->audit($actor, 'tenant.updated', 'tenant', $tenant->id);

            return $tenant;
        });
    }

    public function createPlan(array $data, int $actor): Plan
    {
        return DB::transaction(function () use ($data, $actor) {
            $plan = Plan::create($data);
            $this->audit($actor, 'plan.created', 'plan', $plan->id);

            return $plan;
        });
    }

    public function updatePlan(Plan $plan, array $data, int $actor): Plan
    {
        return DB::transaction(function () use ($plan, $data, $actor) {
            $plan = Plan::lockForUpdate()->findOrFail($plan->id);
            $before = $plan->only(array_keys($data));
            $plan->update($data);
            $changed = collect(array_keys($data))->filter(
                fn (string $key) => json_encode($plan->getAttribute($key)) !== json_encode($before[$key])
            )->values()->all();
            $this->audit($actor, 'plan.updated', 'plan', $plan->id, ['changed' => $changed]);

            // Subscription limits are snapshots. Existing clinic subscriptions are intentionally unchanged.
            return $plan->refresh();
        });
    }

    public function deletePlan(Plan $plan, int $actor): void
    {
        DB::transaction(function () use ($plan, $actor) {
            $plan = Plan::lockForUpdate()->findOrFail($plan->id);
            if (DB::table('subscriptions')->where('plan_id', $plan->id)->exists()) {
                throw ValidationException::withMessages([
                    'plan' => 'This plan cannot be deleted because one or more clinics are subscribed to it. Deactivate or archive it instead.',
                ]);
            }
            $this->audit($actor, 'plan.deleted', 'plan', $plan->id, ['name' => $plan->name]);
            $plan->delete();
        });
    }

    public function savePlatformUser(?User $user, array $data, int $actor): User
    {
        return DB::transaction(function () use ($user,$data,$actor) {
            $user ??= new User();
            if ($user->exists && $user->platformRoles()->where('slug','super-administrator')->exists()) {
                $superRoleId=PlatformRole::where('slug','super-administrator')->value('id');
                $removesSuper=!in_array($superRoleId,$data['role_ids']);
                $activeSupers=DB::table('platform_role_user')->join('users','users.id','=','platform_role_user.user_id')->where('platform_role_id',$superRoleId)->where('users.status','active')->count();
                if (($data['status']==='inactive'||$removesSuper) && $activeSupers<=1) throw ValidationException::withMessages(['role_ids'=>'The last active Super Administrator cannot be disabled or removed from that role.']);
            }
            $user->fill(collect($data)->only(['name','email','password'])->filter(fn($value)=>$value!==null)->all());
            $user->is_platform_admin=true; $user->status=$data['status']; $user->save();
            $user->platformRoles()->sync($data['role_ids']);
            $this->audit($actor,$user->wasRecentlyCreated?'admin.created':'admin.updated','user',$user->id);
            return $user->load('platformRoles:id,name');
        });
    }

    public function savePlatformRole(?PlatformRole $role, array $data, int $actor): PlatformRole
    {
        return DB::transaction(function () use ($role,$data,$actor) {
            $role ??= new PlatformRole();
            if($role->is_system && $role->exists) throw ValidationException::withMessages(['role'=>'System roles cannot be modified.']);
            $role->fill(collect($data)->only(['name','slug','description'])->all())->save();
            $ids=PlatformPermission::whereIn('name',$data['permissions'])->pluck('id');
            $role->permissions()->sync($ids);
            $this->audit($actor,$role->wasRecentlyCreated?'role.created':'role.updated','platform_role',$role->id);
            return $role->load('permissions:id,name,label')->loadCount('users');
        });
    }

    public function updateSubscription(Tenant $tenant, array $data, int $actor): object
    {
        return DB::transaction(function () use ($tenant, $data, $actor) {
            Tenant::lockForUpdate()->findOrFail($tenant->id);
            $plan = Plan::findOrFail($data['plan_id']);
            if (DB::table('branches')->where('tenant_id', $tenant->id)->count() > $plan->branch_limit ||
               DB::table('tenant_memberships')->where('tenant_id', $tenant->id)->where('status', 'active')->count() > $plan->member_limit) {
                throw ValidationException::withMessages(['plan_id' => 'The selected plan is below current clinic usage.']);
            }
            DB::table('subscriptions')->where('tenant_id', $tenant->id)->update([
                'plan_id' => $plan->id, 'status' => $data['status'], 'trial_ends_at' => $data['status'] === 'trial' ? Carbon::parse($data['trial_ends_at'])->utc()->toDateTimeString() : null,
                'branch_limit' => $plan->branch_limit, 'member_limit' => $plan->member_limit, 'updated_at' => now(),
            ]);
            $this->audit($actor, 'subscription.updated', 'tenant', $tenant->id);

            return DB::table('subscriptions')->where('tenant_id', $tenant->id)->first();
        });
    }
}

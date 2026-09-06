<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlatformService
{
    public function audit(int $actor, string $action, string $type, int $id): void
    {
        DB::table('platform_audit_logs')->insert(['actor_id' => $actor, 'action' => $action, 'subject_type' => $type, 'subject_id' => $id, 'created_at' => now()]);
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

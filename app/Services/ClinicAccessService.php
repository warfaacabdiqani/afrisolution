<?php

namespace App\Services;

use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClinicAccessService
{
    public function context(Request $request): array
    {
        $id = $request->session()->get('tenant_id');
        $member = DB::table('tenant_memberships')->where('tenant_id', $id)->where('user_id', $request->user()->id)->where('status', 'active')->first();
        abort_unless($member && $request->user()->status === 'active', 403, 'Select a clinic you belong to.');
        $clinic = DB::table('tenants')->where('id', $id)->firstOrFail();
        $subscription = DB::table('subscriptions')->where('tenant_id', $id)->first();
        $plan = $subscription ? Plan::find($subscription->plan_id) : null;
        $features = $plan?->features ?? [];
        $branches = DB::table('branches')->where('tenant_id', $id)->where('status','active');
        if (!$member->all_branches) {
            $branches->whereIn('id', DB::table('branch_memberships')->where('tenant_id', $id)->where('membership_id', $member->id)->select('branch_id'));
        }
        // A single-branch plan retains only the clinic's original branch.
        if (!($features['multi_branch'] ?? false)) {
            $branches->where('id', DB::table('branches')->where('tenant_id', $id)->min('id'));
        }
        $branches = $branches->orderBy('id')->get(['id', 'name']);
        $selected = $request->session()->get('branch_id');
        $branch = $branches->firstWhere('id', $selected) ?? $branches->first();
        $request->session()->put('branch_id', $branch?->id);
        $permissions = $member->permissions === null ? config('clinic.roles.'.$member->role, []) : json_decode($member->permissions, true);
        $policy=app(ClinicSettingsService::class)->section((int)$id,'security');
        $manager=$this->can($permissions,'clinic_settings.update') || $this->can($permissions,'clinic_settings.security.update');
        $staffAllowed=$policy['allow_staff_login'] || $manager;
        $timeout=min((int)$policy['session_timeout'],(int)app(SystemSettingsService::class)->get('security.session_lifetime',120));
        $activityKey='clinic_activity.'.$id;
        $last=$request->session()->get($activityKey);
        if($last && now()->timestamp-$last>$timeout*60) {
            app(SessionService::class)->logout($request);
            abort(401,'Your clinic session expired due to inactivity. Please sign in again.');
        }
        $request->session()->put($activityKey,now()->timestamp);
        $active = $staffAllowed && $clinic->status === 'active' && $subscription && ($subscription->status === 'active' || ($subscription->status === 'trial' && $subscription->trial_ends_at && now()->lt($subscription->trial_ends_at)));
        $modules = collect(config('clinic.modules'))->map(function ($module, $key) use ($permissions, $features) {
            [$label, $permission, $feature, $icon, $group] = $module;
            return compact('key', 'label', 'permission', 'feature', 'icon', 'group') + ['allowed' => $this->can($permissions, $permission) && (!$feature || ($features[$feature] ?? false))];
        })->values();
        return [
            'clinic' => $clinic, 'branch' => $branch, 'branches' => $branches,
            'role' => $member->role, 'permissions' => $permissions, 'features' => $features,
            'subscription' => $subscription, 'plan' => $plan?->only(['name', 'currency']),
            'limits' => array_merge($plan?->only(['doctor_limit', 'patient_limit', 'storage_limit_gb', 'appointment_limit', 'invoice_limit']) ?? [], ['branch_limit' => $subscription?->branch_limit, 'member_limit' => $subscription?->member_limit]),
            'operational' => (bool) $active && $branch !== null,
            'restriction' => !$staffAllowed ? 'Staff access to this clinic is disabled. Contact your clinic administrator.' : (!$active ? 'Your clinic or subscription is inactive. Contact your clinic administrator for assistance.' : (!$branch ? 'No authorized branch is available. Contact your clinic administrator.' : null)),
            'modules' => $modules, 'idle_timeout_minutes' => $timeout,
            'today' => now($clinic->timezone)->toDateString(),
        ];
    }

    public function can(array $permissions, string $permission): bool
    {
        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    public function authorize(Request $request, string $module, ?string $permission = null): array
    {
        $context = $this->context($request);
        abort_unless($context['operational'], 403, $context['restriction']);
        $entry = $context['modules']->firstWhere('key', $module);
        abort_unless($entry && $entry['allowed'], 403, 'Your permissions or plan do not allow access to this module.');
        if ($permission) abort_unless($this->can($context['permissions'], $permission), 403, 'You do not have permission for this action.');
        if ($request->hasHeader('X-Branch-Context')) abort_unless((string) $context['branch']->id === $request->header('X-Branch-Context'), 409, 'The active branch changed. Refresh this page.');
        return $context;
    }
}

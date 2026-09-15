<?php

namespace App\Services;

use App\Models\BusinessType;
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
        $businessType = BusinessType::find($clinic->business_type_id) ?? BusinessType::where('slug', 'clinic')->firstOrFail();
        $businessProfile = app(BusinessProfileService::class)->resolveBusinessType($businessType);
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
        $modules = collect(config('clinic.modules'))->map(function ($module, $key) use ($permissions, $features, $businessProfile) {
            [$label, $permission, $feature, $icon, $group] = $module;
            $businessModules = config('clinic.business_module_map.'.$key, []);
            $businessEnabled = count($businessModules) > 0 && collect($businessModules)->every(fn ($capability) => !empty($businessProfile['modules'][$capability]));
            if ($key === 'settings') $label = $businessProfile['settings_label'];
            if ($key === 'patients') $label = $businessProfile['labels']['customers'];
            if ($key === 'appointments') $label = $businessProfile['labels']['bookings'];
            return compact('key', 'label', 'permission', 'feature', 'icon', 'group') + [
                'business_modules' => $businessModules, 'business_allowed' => $businessEnabled,
                'allowed' => $businessEnabled && (!$feature || ($features[$feature] ?? false)) && $this->can($permissions, $permission),
            ];
        })->values();
        return [
            'clinic' => $clinic, 'branch' => $branch, 'branches' => $branches,
            'role' => $member->role, 'permissions' => $permissions, 'features' => $features,
            'subscription' => $subscription, 'plan' => $plan?->only(['name', 'currency']),
            'limits' => array_merge($plan?->only(['doctor_limit', 'patient_limit', 'storage_limit_gb', 'appointment_limit', 'invoice_limit']) ?? [], ['branch_limit' => $subscription?->branch_limit, 'member_limit' => $subscription?->member_limit]),
            'business_type' => [
                'id' => $businessProfile['id'],
                'slug' => $businessProfile['slug'],
                'name' => $businessProfile['name'],
                'category' => $businessProfile['category'],
                'navigation_profile_key' => $businessProfile['navigation_profile_key'] ?? null,
                'dashboard_profile_key' => $businessProfile['dashboard_profile_key'] ?? null,
            ],
            'labels' => $businessProfile['labels'] ?? [],
            'business_modules' => $businessProfile['modules'] ?? [],
            'settings_business_access' => collect(array_keys(config('clinic_settings')))->mapWithKeys(fn ($section) => [$section => app(ClinicSettingsService::class)->businessAllowed(['business_modules' => $businessProfile['modules']], $section)])->all(),
            'navigation_profile_key' => $businessProfile['navigation_profile_key'] ?? null,
            'dashboard_profile_key' => $businessProfile['dashboard_profile_key'] ?? null,
            'business_profile' => [
                'name' => $businessProfile['name'],
                'subtitle' => $businessProfile['subtitle'] ?? null,
                'settings_label' => $businessProfile['settings_label'] ?? 'Clinic Settings',
                'navigation_profile' => $businessProfile['navigation_profile'] ?? [],
                'dashboard_profile' => $businessProfile['dashboard_profile'] ?? [],
                'labels' => $businessProfile['labels'] ?? [],
                'modules' => $businessProfile['modules'] ?? [],
            ],
            'restriction_code' => !$staffAllowed || !$branch ? 'PERMISSION_DENIED' : (!$active ? 'SUBSCRIPTION_INACTIVE' : null),
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
        if (!$context['operational']) $this->deny($context['restriction_code'], $context['restriction']);

        $entry = $context['modules']->firstWhere('key', $module);
        abort_unless($entry, 403, 'This module is not available.');

        $this->authorizeBusiness($context, $module);
        if ($module === 'appointments' && $request->is('api/v1/clinic/*') && empty($context['business_modules']['clinical'])) {
            $this->deny('BUSINESS_MODULE_UNAVAILABLE', 'This appointment API is for healthcare businesses.');
        }

        $hasFeature = ! $entry['feature'] || (($context['features'][$entry['feature']] ?? false));
        if (!$hasFeature) $this->deny('PLAN_FEATURE_UNAVAILABLE', 'Your current plan does not include this feature.');

        if (!$this->can($context['permissions'], $entry['permission'])) $this->deny('PERMISSION_DENIED', 'You do not have permission to access this module.');

        if ($permission) {
            if (!$this->can($context['permissions'], $permission)) $this->deny('PERMISSION_DENIED', 'You do not have permission for this action.');
        }

        if ($request->hasHeader('X-Branch-Context')) abort_unless((string) $context['branch']->id === $request->header('X-Branch-Context'), 409, 'The active branch changed. Refresh this page.');
        return $context;
    }

    public function authorizeBusiness(array $context, string $module): void
    {
        $requirements = config('clinic.business_module_map.'.$module, []);
        $tenant = \App\Models\Tenant::findOrFail($context['clinic']->id);
        if (!$requirements || !collect($requirements)->every(fn ($key) => app(BusinessProfileService::class)->moduleEnabled($tenant, $key))) {
            $label = config('clinic.modules.'.$module.'.0', ucfirst($module));
            $this->deny('BUSINESS_MODULE_UNAVAILABLE', $label.' are not available for '.$context['business_type']['name'].' businesses.');
        }
    }

    public function deny(string $code, string $message): never
    {
        throw new \Illuminate\Http\Exceptions\HttpResponseException(response()->json(compact('code', 'message'), 403));
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\BusinessType;
use App\Models\Plan;
use App\Models\User;
use App\Services\PlatformService;
use App\Services\SystemSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Support\RegistrationRules;

class RegistrationController extends Controller
{
    public function options(SystemSettingsService $settings)
    {
        return response()->json(['data' => [
            'enabled' => (bool) $settings->get('general.registration_enabled', true),
            'business_types' => BusinessType::where('status', 'active')->orderBy('name')->get(['id', 'name', 'slug']),
            'plans' => Plan::where('status', 'active')->where('trial_days', '>', 0)->orderBy('price')->get(['id', 'name', 'description', 'price', 'currency', 'billing_period', 'trial_days']),
        ]]);
    }

    public function validateStep(Request $request, SystemSettingsService $settings)
    {
        abort_unless((bool) $settings->get('general.registration_enabled', true), 403, 'Registration is unavailable.');
        $step = $request->validate(['step' => ['required', 'integer', 'between:1,4']])['step'];
        $request->validate(RegistrationRules::forStep((int) $step));

        // Validation only: no account/session changes, provisioning, or notifications.
        return response()->json(['valid' => true]);
    }

    public function store(Request $request, PlatformService $platform, SystemSettingsService $settings)
    {
        abort_unless((bool) $settings->get('general.registration_enabled', true), 403, 'Registration is unavailable.');
        $data = $request->validate(RegistrationRules::all());
        unset($data['owner_password_confirmation']);

        [$user, $tenant, $subscription, $plan] = DB::transaction(function () use ($data, $platform) {
            $user = User::create(['name' => $data['owner_name'], 'email' => $data['owner_email'], 'password' => $data['owner_password']]);
            $tenant = $platform->createTenant($data + ['owner_user_id' => $user->id], $user->id);
            $subscription = DB::table('subscriptions')->where('tenant_id', $tenant->id)->first();
            return [$user, $tenant, $subscription, Plan::findOrFail($data['plan_id'])];
        });

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put('tenant_id', $tenant->id);

        $emailSent = true;
        try { $user->sendEmailVerificationNotification(); }
        catch (\Throwable) { $emailSent = false; }

        return response()->json(['data' => [
            'business_name' => $tenant->name,
            'business_type' => $tenant->businessType->name,
            'plan' => $plan->name,
            'trial_ends_at' => $subscription->trial_ends_at,
            'tenant_id' => $tenant->id,
            'email' => $user->email,
            'email_verification_required' => true,
            'verification_email_sent' => $emailSent,
        ]], 201);
    }
}

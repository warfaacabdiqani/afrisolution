<?php

namespace App\Services;

use App\Models\BusinessType;
use App\Models\Tenant;

class BusinessProfileService
{
    public function resolveTenant(Tenant $tenant): array
    {
        $businessType = $tenant->businessType()->firstOrFail();

        return $this->resolveBusinessType($businessType);
    }

    public function resolveBusinessType(BusinessType $businessType): array
    {
        $profile = config('business_types.'.$businessType->slug, config('business_types.clinic'));

        return [
            'id' => $businessType->id,
            'slug' => $businessType->slug,
            'name' => $businessType->name,
            'category' => $businessType->category,
            'subtitle' => $profile['subtitle'] ?? $profile['name'] ?? null,
            'settings_label' => $profile['settings_label'] ?? 'Clinic Settings',
            'dashboard_profile_key' => $profile['dashboard_profile_key'] ?? null,
            'navigation_profile_key' => $profile['navigation_profile_key'] ?? null,
            'dashboard_profile' => $profile['dashboard_profile'] ?? [],
            'navigation_profile' => $profile['navigation_profile'] ?? [],
            'labels' => $profile['labels'] ?? [],
            'modules' => $profile['modules'] ?? [],
        ];
    }

    public function moduleEnabled(Tenant $tenant, string $module): bool
    {
        return (bool) ($this->resolveTenant($tenant)['modules'][$module] ?? false);
    }
}

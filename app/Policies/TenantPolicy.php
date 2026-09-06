<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

class TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->is_platform_admin;
    }

    public function create(User $user): bool
    {
        return (bool) $user->is_platform_admin;
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return (bool) $user->is_platform_admin;
    }
}

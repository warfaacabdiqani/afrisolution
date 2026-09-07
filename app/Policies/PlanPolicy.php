<?php

namespace App\Policies;

use App\Models\Plan;
use App\Models\User;

class PlanPolicy
{
    public function before(User $user): ?bool
    {
        return $user->is_platform_admin ? true : null;
    }

    public function viewAny(User $user): bool { return false; }
    public function view(User $user, Plan $plan): bool { return false; }
    public function create(User $user): bool { return false; }
    public function update(User $user, Plan $plan): bool { return false; }
}

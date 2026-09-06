<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\TenantMembership;
use App\Models\User;
use App\Tenancy\TenantContext;

class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return TenantMembership::where('user_id', $user->id)->where('status', 'active')->exists();
    }

    public function view(User $user, Branch $branch): bool
    {
        return (int) $branch->tenant_id === app(TenantContext::class)->id() && $this->viewAny($user);
    }
}

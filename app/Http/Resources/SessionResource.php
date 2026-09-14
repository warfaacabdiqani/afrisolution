<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class SessionResource extends JsonResource
{
    public function toArray($request): array
    {
        // Identity lookup is intentionally outside tenant scopes, constrained to this user.
        $clinics = DB::table('tenant_memberships')->join('tenants', 'tenants.id', '=', 'tenant_memberships.tenant_id')
            ->where('user_id', $this->id)->where('tenant_memberships.status', 'active')->where('tenants.status', 'active')
            ->select('tenants.id', 'tenants.name', 'tenant_memberships.role')->orderBy('tenants.name')->get();
        $selected = $request->session()->get('tenant_id');

        return ['id' => $this->id, 'name' => $this->name, 'email' => $this->email, 'is_platform_admin' => (bool) $this->is_platform_admin,
            'platform_permissions' => $this->is_platform_admin && $this->status === 'active'
                ? DB::table('platform_permissions as p')->join('platform_permission_role as pr', 'pr.platform_permission_id', '=', 'p.id')
                    ->join('platform_role_user as ru', 'ru.platform_role_id', '=', 'pr.platform_role_id')
                    ->where('ru.user_id', $this->id)->distinct()->pluck('p.name')->all() : [],
            'clinics' => $clinics, 'active_tenant_id' => $clinics->contains('id', $selected) ? $selected : null];
    }
}

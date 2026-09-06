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
            'clinics' => $clinics, 'active_tenant_id' => $clinics->contains('id', $selected) ? $selected : null];
    }
}

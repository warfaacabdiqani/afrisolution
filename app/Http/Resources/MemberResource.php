<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class MemberResource extends JsonResource
{
    public function toArray($request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'email' => $this->email, 'role' => $this->role, 'status' => $this->status,
            'all_branches' => (bool) $this->all_branches, 'branch_ids' => $this->branch_ids, 'permissions' => $this->permissions === null ? null : json_decode($this->permissions, true)];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BusinessTypeResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'category' => $this->category,
            'description' => $this->description,
            'icon' => $this->icon,
            'status' => $this->status,
            'sort_order' => $this->sort_order,
            'tenant_count' => (int) ($this->tenants_count ?? $this->tenants()->count()),
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray($request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'slug' => $this->slug, 'status' => $this->status, 'timezone' => $this->timezone, 'created_at' => $this->created_at];
    }
}

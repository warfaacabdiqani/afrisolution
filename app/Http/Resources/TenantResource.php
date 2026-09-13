<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'slug' => $this->slug,
            'status' => $this->status, 'timezone' => $this->timezone, 'created_at' => $this->created_at,
            'business_type' => $this->whenLoaded('businessType', fn () => [
                'id' => $this->businessType->id,
                'slug' => $this->businessType->slug,
                'name' => $this->businessType->name,
            ]),
            'owner' => $this->when(array_key_exists('owner_name', $this->getAttributes()), [
                'name' => $this->owner_name, 'email' => $this->owner_email,
            ]),
            'plan_name' => $this->when(array_key_exists('plan_name', $this->getAttributes()), $this->plan_name),
            'subscription_status' => $this->when(array_key_exists('subscription_status', $this->getAttributes()), $this->subscription_status),
            'trial_ends_at' => $this->when(array_key_exists('trial_ends_at', $this->getAttributes()), $this->trial_ends_at),
            'members_count' => $this->when(array_key_exists('members_count', $this->getAttributes()), (int) $this->members_count),
            'branches_count' => $this->when(array_key_exists('branches_count', $this->getAttributes()), (int) $this->branches_count),
        ];
    }
}

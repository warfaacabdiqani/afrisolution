<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray($request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'branch_limit' => $this->branch_limit, 'member_limit' => $this->member_limit, 'trial_days' => $this->trial_days];
    }
}

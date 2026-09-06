<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class SubscriptionResource extends JsonResource
{
    public function toArray($request): array
    {
        return ['plan_id' => $this->plan_id, 'status' => $this->status, 'trial_ends_at' => $this->trial_ends_at ? Carbon::parse($this->trial_ends_at, 'UTC')->toISOString() : null, 'branch_limit' => $this->branch_limit, 'member_limit' => $this->member_limit];
    }
}

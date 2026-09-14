<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'slug' => $this->slug,
            'description' => $this->description, 'status' => $this->status,
            'price' => (string) $this->price, 'currency' => $this->currency,
            'billing_period' => $this->billing_period, 'trial_days' => $this->trial_days,
            'branch_limit' => $this->branch_limit, 'member_limit' => $this->member_limit,
            'doctor_limit' => $this->doctor_limit, 'patient_limit' => $this->patient_limit,
            'storage_limit_gb' => $this->storage_limit_gb, 'appointment_limit' => $this->appointment_limit,
            'client_limit' => $this->client_limit, 'service_limit' => $this->service_limit,
            'invoice_limit' => $this->invoice_limit, 'features' => $this->features ?? [],
            'subscriptions_count' => $this->whenCounted('subscriptions'),
            'active_subscriptions_count' => $this->when(isset($this->active_subscriptions_count), $this->active_subscriptions_count),
            'trial_subscriptions_count' => $this->when(isset($this->trial_subscriptions_count), $this->trial_subscriptions_count),
            'created_at' => $this->created_at?->toISOString(), 'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

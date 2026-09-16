<?php

namespace App\Http\Resources;

use App\Services\Billing\BillingMoney;
use Illuminate\Http\Resources\Json\JsonResource;

/** Minimal completed-charge summary; no contact details, notes or booking actions. */
class SalonBillingSourceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id, 'appointment_number' => $this->appointment_number,
            'customer' => ['type' => 'salon_client', 'id' => $this->client_id, 'name' => $this->client?->full_name],
            'branch' => ['id' => $this->branch_id, 'name' => $this->branch?->name],
            'currency' => $this->currency, 'total' => BillingMoney::decimal(BillingMoney::cents($this->total)),
        ];
    }
}

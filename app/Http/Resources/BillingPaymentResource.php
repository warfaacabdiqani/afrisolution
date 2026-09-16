<?php

namespace App\Http\Resources;

use App\Services\Billing\BillingMoney;
use Illuminate\Http\Resources\Json\JsonResource;

class BillingPaymentResource extends JsonResource
{
    public function toArray($request): array
    {
        return $this->resource->only(['id', 'method', 'reference', 'paid_at']) + ['amount' => BillingMoney::decimal(BillingMoney::cents($this->amount))];
    }
}

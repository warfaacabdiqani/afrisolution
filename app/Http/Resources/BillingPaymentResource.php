<?php

namespace App\Http\Resources;

use App\Services\Billing\BillingMoney;
use Illuminate\Http\Resources\Json\JsonResource;

class BillingPaymentResource extends JsonResource
{
    public function toArray($request): array
    {
        return $this->resource->only(['id', 'method', 'reference', 'paid_at']) + [
            'amount' => BillingMoney::decimal(BillingMoney::cents($this->amount)),
            'receipt' => $this->whenLoaded('receipt', fn () => $this->receipt ? ['id' => $this->receipt->id, 'number' => $this->receipt->number] : null),
            'recorded_by_name' => $this->receipt?->snapshot['recorded_by_name'] ?? $this->whenLoaded('recordedBy', fn () => $this->recordedBy?->name),
        ];
    }
}

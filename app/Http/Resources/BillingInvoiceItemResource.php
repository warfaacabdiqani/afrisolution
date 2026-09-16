<?php

namespace App\Http\Resources;

use App\Services\Billing\BillingMoney;
use Illuminate\Http\Resources\Json\JsonResource;

class BillingInvoiceItemResource extends JsonResource
{
    public function toArray($request): array
    {
        $data = $this->resource->only(['id', 'description', 'quantity']);
        foreach (['unit_price', 'amount', 'discount_amount', 'tax_rate', 'tax_amount', 'line_total'] as $field) {
            $data[$field] = $this->$field === null ? null : BillingMoney::decimal(BillingMoney::cents($this->$field));
        }
        return $data + ['source' => $this->source_type ? ['type' => $this->source_type, 'id' => $this->source_id] : null];
    }
}

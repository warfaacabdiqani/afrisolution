<?php

namespace App\Http\Resources;

use App\Services\Billing\BillingMoney;
use Illuminate\Http\Resources\Json\JsonResource;

class BillingInvoiceResource extends JsonResource
{
    public function toArray($request): array
    {
        $data = $this->resource->only(['id', 'number', 'tenant_id', 'branch_id', 'customer_name', 'status', 'currency',
            'snapshot_version']);
        foreach (['created_at', 'issued_at', 'due_at'] as $field) {
            $data[$field] = $this->$field ? \Illuminate\Support\Carbon::parse($this->$field)->utc()->format('Y-m-d\TH:i:s\Z') : null;
        }
        foreach (['subtotal', 'discount', 'tax', 'credit', 'total', 'paid', 'tax_rate'] as $field) {
            $data[$field] = $this->$field === null ? null : BillingMoney::decimal(BillingMoney::cents($this->$field));
        }
        return $data + [
            'branch' => $this->branch ? ['id' => $this->branch->id, 'name' => $this->branch->name] : null,
            'customer' => ['type' => $this->patient_id ? 'patient' : ($this->salon_client_id ? 'salon_client' : null),
                'id' => $this->patient_id ?? $this->salon_client_id, 'name' => $this->customer_name],
            'source' => ['type' => $this->source_type, 'id' => $this->source_id],
            'balance' => BillingMoney::balance($this->resource),
            'items' => BillingInvoiceItemResource::collection($this->whenLoaded('items')),
            'payments' => BillingPaymentResource::collection($this->whenLoaded('payments')),
        ];
    }
}

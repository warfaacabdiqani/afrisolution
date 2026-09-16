<?php

namespace App\Services\Billing;

use App\Services\SalonBookingService;
use Illuminate\Validation\ValidationException;

class SalonBillingAdapter implements InvoiceSource
{
    public function snapshot(array $context, int $id): array
    {
        abort_unless($context['business_type']['slug'] === 'beauty-salon', 403);
        $appointment = app(SalonBookingService::class)->find($context, $id);
        if ($appointment->status !== 'completed') {
            throw ValidationException::withMessages(['status' => 'Complete the appointment before creating its invoice.']);
        }
        return [
            'tenant_id' => $appointment->tenant_id, 'branch_id' => $appointment->branch_id,
            'source_id' => $appointment->id, 'customer_type' => 'salon_client', 'customer_id' => $appointment->client_id,
            'currency' => $appointment->currency, 'discount' => $appointment->discount, 'tax_rate' => $appointment->tax_rate,
            'expected_totals' => $appointment->only(['subtotal', 'discount', 'tax', 'total']),
            'items' => $appointment->items->map(fn ($item) => [
                'source_type' => 'salon_appointment_service', 'source_id' => $item->id,
                'description' => $item->name, 'quantity' => 1, 'unit_price' => $item->unit_price,
            ])->all(),
            'audit' => ['appointment_id' => $appointment->id],
        ];
    }
}

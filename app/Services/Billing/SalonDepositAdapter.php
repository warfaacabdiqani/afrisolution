<?php

namespace App\Services\Billing;

use App\Services\SalonBookingService;
use Illuminate\Validation\ValidationException;

class SalonDepositAdapter implements DepositSource
{
    public function snapshot(array $context, int $id): array
    {
        abort_unless($context['business_type']['slug'] === 'beauty-salon', 403);
        $appointment = app(SalonBookingService::class)->visible($context)->with('client')->findOrFail($id);
        if (in_array($appointment->status, ['completed', 'cancelled', 'no_show'], true)) {
            throw ValidationException::withMessages(['status' => 'This booking can no longer receive a deposit.']);
        }
        $required = BillingMoney::cents($appointment->deposit_required);
        if ($required <= 0) throw ValidationException::withMessages(['deposit' => 'This booking does not require a deposit.']);
        return [
            'tenant_id' => $appointment->tenant_id, 'branch_id' => $appointment->branch_id, 'source_id' => $appointment->id,
            'customer_type' => 'salon_client', 'customer_id' => $appointment->client_id, 'currency' => $appointment->currency,
            'discount' => '0.00', 'tax_rate' => '0.00',
            'items' => [[ 'source_type' => 'salon_appointment_deposit', 'source_id' => $appointment->id,
                'description' => 'Booking deposit - '.$appointment->appointment_number, 'quantity' => 1,
                'unit_price' => BillingMoney::decimal($required) ]],
            'expected_totals' => ['total' => BillingMoney::decimal($required)],
            'audit' => ['business_type' => 'beauty-salon', 'appointment_id' => $appointment->id,
                'branch_id' => $appointment->branch_id, 'salon_client_id' => $appointment->client_id],
        ];
    }
}

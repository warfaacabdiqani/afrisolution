<?php

namespace App\Services\Billing;

use App\Services\AppointmentService;
use Illuminate\Validation\ValidationException;

class ClinicAppointmentDepositAdapter implements DepositSource
{
    public function snapshot(array $context, int $id): array
    {
        abort_unless(!empty($context['business_modules']['clinical']), 403);
        $appointment = app(AppointmentService::class)->find($context, $id);
        if (in_array($appointment->status, ['completed', 'cancelled', 'no_show'], true)) {
            throw ValidationException::withMessages(['status' => 'This appointment can no longer receive a deposit.']);
        }
        $required = BillingMoney::cents($appointment->deposit_required);
        if (!$required) throw ValidationException::withMessages(['deposit' => 'This appointment does not require a deposit.']);
        return [
            'tenant_id' => $appointment->tenant_id, 'branch_id' => $appointment->branch_id, 'source_id' => $appointment->id,
            'customer_type' => 'patient', 'customer_id' => $appointment->patient_id,
            'currency' => app(\App\Services\ClinicSettingsService::class)->get($appointment->tenant_id, 'billing.currency', 'USD'),
            'discount' => '0.00', 'tax_rate' => '0.00',
            'items' => [['source_type' => 'clinic_appointment_deposit', 'source_id' => $appointment->id,
                'description' => 'Consultation deposit - '.$appointment->appointment_number, 'quantity' => 1,
                'unit_price' => BillingMoney::decimal($required)]],
            'expected_totals' => ['total' => BillingMoney::decimal($required)],
            'audit' => ['business_type' => $context['business_type']['slug'], 'appointment_id' => $appointment->id,
                'patient_id' => $appointment->patient_id, 'branch_id' => $appointment->branch_id],
        ];
    }
}

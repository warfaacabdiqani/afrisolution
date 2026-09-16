<?php

namespace App\Services\Billing;

use App\Services\{AppointmentService, ClinicSettingsService};
use Illuminate\Validation\ValidationException;

class ClinicAppointmentBillingAdapter implements InvoiceSource
{
    public function snapshot(array $context, int $id): array
    {
        abort_unless(!empty($context['business_modules']['clinical']), 403, 'Clinic billing sources require a healthcare business.');
        $appointment = app(AppointmentService::class)->find($context, $id);
        if ($appointment->status !== 'completed') {
            throw ValidationException::withMessages(['status' => 'Complete the consultation before creating its invoice.']);
        }
        // Patients are tenant-wide; their registration branch need not be the treatment branch.
        if (!$appointment->patient || (int) $appointment->patient->tenant_id !== (int) $appointment->tenant_id) {
            throw ValidationException::withMessages(['patient' => 'The appointment must belong to a patient in this business.']);
        }
        if (!$appointment->doctor) throw ValidationException::withMessages(['doctor' => 'The appointment clinician is unavailable.']);
        $billing = app(ClinicSettingsService::class)->section($appointment->tenant_id, 'billing');
        $fee = $appointment->doctor->consultation_fee ?? $billing['consultation_fee'];

        return [
            'tenant_id' => $appointment->tenant_id, 'branch_id' => $appointment->branch_id, 'source_id' => $appointment->id,
            'customer_type' => 'patient', 'customer_id' => $appointment->patient_id,
            'currency' => $billing['currency'], 'discount' => '0.00', 'tax_rate' => $billing['tax_rate'],
            'items' => [[
                'source_type' => 'clinic_appointment', 'source_id' => $appointment->id,
                'description' => 'Consultation - Dr. '.$appointment->doctor->full_name,
                'quantity' => 1, 'unit_price' => $fee,
            ]],
            'audit' => ['business_type' => $context['business_type']['slug'], 'appointment_id' => $appointment->id,
                'patient_id' => $appointment->patient_id, 'branch_id' => $appointment->branch_id],
        ];
    }
}

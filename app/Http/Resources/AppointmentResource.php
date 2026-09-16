<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray($request): array
    {
        $billing = app(\App\Services\ClinicSettingsService::class)->section($this->tenant_id, 'billing');
        $doctorFee = $this->doctor?->consultation_fee;

        if ($doctorFee !== null) {
            $consultationFee = (float) $doctorFee;
            $consultationFeeSource = 'doctor';
        } else {
            $consultationFee = (float) ($billing['consultation_fee'] ?? 0);
            $consultationFeeSource = 'clinic';
        }

        return [
            'invoice_id' => $this->whenLoaded('invoice', fn () => $this->invoice->id),
            'id' => $this->id, 'appointment_number' => $this->appointment_number, 'branch_id' => $this->branch_id, 'patient_id' => $this->patient_id, 'doctor_id' => $this->doctor_id, 'appointment_type_id' => $this->appointment_type_id,
            'starts_at' => $this->starts_at, 'ends_at' => $this->ends_at, 'status' => $this->status, 'source' => $this->source, 'is_walk_in' => $this->is_walk_in,
            'consultation_fee' => $consultationFee,
            'consultation_fee_source' => $consultationFeeSource,
            'currency' => $billing['currency'] ?? 'USD',
            'patient' => ['id' => $this->patient->id, 'full_name' => $this->patient->full_name, 'patient_number' => $this->patient->patient_number, 'phone' => $this->patient->phone],
            'doctor' => ['id' => $this->doctor->id, 'full_name' => $this->doctor->full_name], 'branch' => $this->branch->name, 'type' => $this->type?->name,
        ];
    }
}

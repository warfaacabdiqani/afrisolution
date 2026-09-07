<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PatientResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id, 'patient_number' => $this->patient_number, 'full_name' => $this->full_name,
            'first_name' => $this->first_name, 'middle_name' => $this->middle_name, 'last_name' => $this->last_name,
            'gender' => $this->gender, 'age' => $this->age, 'age_is_estimated' => $this->reported_age !== null && !$this->date_of_birth,
            'phone' => $this->phone, 'blood_group' => $this->blood_group, 'registered_at' => $this->registered_at->toIso8601String(), 'status' => $this->status,
        ];
    }
}

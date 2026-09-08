<?php

namespace App\Http\Requests;

use App\Services\ClinicAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        app(ClinicAccessService::class)->authorize($this, 'appointments', $this->routeIs('appointments.reschedule') ? 'appointments.reschedule' : ($this->isMethod('PUT') ? 'appointments.update' : 'appointments.create'));

        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer'], 'patient_id' => ['required', 'integer'], 'doctor_id' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'], 'start_time' => ['required', 'date_format:H:i'], 'duration' => ['required', 'integer', 'between:5,480'],
            'appointment_type_id' => ['nullable', 'integer'], 'reason' => ['nullable', 'string', 'max:2000'], 'notes' => ['nullable', 'string', 'max:5000'],
            'source' => ['required', Rule::in(['clinic', 'phone', 'online', 'walk_in'])], 'status' => ['sometimes', Rule::in(['scheduled', 'waiting'])],
            'override_schedule' => ['sometimes', 'boolean'], 'override_reason' => ['required_if:override_schedule,true', 'nullable', 'string', 'max:500'],
            'tenant_id' => ['prohibited'], 'appointment_number' => ['prohibited'], 'created_by' => ['prohibited'],
        ];
    }
}

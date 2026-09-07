<?php

namespace App\Http\Requests;

use App\Services\ClinicAccessService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        $context = app(ClinicAccessService::class)->authorize($this, 'patients', $this->isMethod('POST') ? 'patients.create' : 'patients.update');
        if ($this->filled('notes') || $this->filled('allergy') || $this->filled('condition')) {
            abort_unless(app(ClinicAccessService::class)->can($context['permissions'], 'patients.medical_history.update'), 403, 'You cannot edit medical history.');
        }
        return true;
    }
    public function rules(): array
    {
        $rules = [
            'first_name' => ['required', 'string', 'max:100'], 'middle_name' => ['nullable', 'string', 'max:100'], 'last_name' => ['required', 'string', 'max:100'],
            'gender' => ['required', Rule::in(['male', 'female', 'other', 'unknown'])],
            'date_of_birth' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'blood_group' => ['nullable', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])],
            'marital_status' => ['nullable', Rule::in(['single', 'married', 'divorced', 'widowed', 'other'])],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[+0-9() .\-]{5,40}$/'],
            'email' => ['nullable', 'email', 'max:255'], 'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:100'], 'country' => ['nullable', 'string', 'max:100'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'], 'emergency_contact_relationship' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:40', 'regex:/^[+0-9() .\-]{5,40}$/'],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])], 'notes' => ['nullable', 'string', 'max:5000'],
            'allergy' => [$this->isMethod('POST') ? 'nullable' : 'prohibited', 'string', 'max:150'],
            'condition' => [$this->isMethod('POST') ? 'nullable' : 'prohibited', 'string', 'max:150'],
            'confirm_duplicate' => ['sometimes', 'boolean'],
        ];
        foreach (['id', 'tenant_id', 'branch_id', 'registration_branch_id', 'patient_number', 'registered_at', 'created_by', 'updated_by', 'archived_at'] as $field) $rules[$field] = ['prohibited'];
        return $rules;
    }
}

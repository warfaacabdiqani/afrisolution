<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_platform_admin;
    }

    public function rules(): array
    {
        $plan = $this->route('plan');

        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'alpha_dash', 'max:100', Rule::unique('plans')->ignore($plan)],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['active', 'inactive', 'archived'])],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'currency' => ['required', 'string', 'size:3'],
            'billing_period' => ['required', Rule::in(['monthly', 'quarterly', 'yearly', 'custom'])],
            'trial_days' => ['required', 'integer', 'between:0,365'],
            'branch_limit' => ['nullable', 'integer', 'between:1,10000'],
            'member_limit' => ['nullable', 'integer', 'between:1,100000'],
            'doctor_limit' => ['nullable', 'integer', 'between:1,100000'],
            'patient_limit' => ['nullable', 'integer', 'between:1,100000000'],
            'storage_limit_gb' => ['nullable', 'integer', 'between:1,1000000'],
            'appointment_limit' => ['nullable', 'integer', 'between:1,100000000'],
            'invoice_limit' => ['nullable', 'integer', 'between:1,100000000'],
            'client_limit' => ['nullable','integer','between:1,100000000'],
            'service_limit' => ['nullable','integer','between:1,100000000'],
            'features' => ['present', 'array'],
            'features.*' => ['boolean'],
            'tenant_id' => ['prohibited'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('currency')) $this->merge(['currency' => strtoupper((string) $this->currency)]);
    }

    public function after(): array
    {
        return [function ($validator) {
            $unknown = array_diff(array_keys($this->input('features', [])), config('plan_features'));
            if ($unknown) $validator->errors()->add('features', 'One or more plan features are not supported.');
        }];
    }
}

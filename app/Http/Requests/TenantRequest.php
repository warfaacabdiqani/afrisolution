<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class TenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_platform_admin;
    }

    public function rules(): array
    {
        $rules = ['name' => ['required', 'string', 'max:150'], 'timezone' => ['required', 'timezone'], 'tenant_id' => ['prohibited'], 'is_platform_admin' => ['prohibited'], 'location_name' => ['nullable', 'string', 'max:150']];

        if ($this->isMethod('POST')) {
            return $rules + [
                'slug' => ['required', 'alpha_dash:ascii', 'max:80', 'unique:tenants,slug'],
                'business_type_id' => ['nullable', 'integer', Rule::exists('business_types', 'id')->where(fn ($query) => $query->where('status', 'active'))],
                'owner_name' => ['required', 'string', 'max:150'], 'owner_email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'owner_password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
                'plan_id' => ['required', 'integer', 'exists:plans,id'],
            ];
        }

        return $rules + ['status' => ['required', Rule::in(['active', 'suspended'])]];
    }
}

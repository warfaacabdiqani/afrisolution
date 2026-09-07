<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class MemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_platform_admin;
    }

    public function rules(): array
    {
        $rules = ['tenant_id' => ['prohibited'], 'is_platform_admin' => ['prohibited'], 'role' => ['required', Rule::in(array_keys(config('clinic.roles')))]];
        $rules += [
            'all_branches' => ['sometimes', 'boolean'],
            'branch_ids' => ['required_if:all_branches,false', 'array', 'min:1'],
            'branch_ids.*' => ['integer', 'distinct', Rule::exists('branches', 'id')->where('tenant_id', $this->route('tenant')->id)],
            'permissions' => ['sometimes', 'nullable', 'array'],
            'permissions.*' => ['string', Rule::in(array_values(array_unique(array_merge(['*', 'doctors.view', 'clinic_settings.view', 'clinic_settings.update', 'staff.manage', 'patients.archive'], ...array_values(config('clinic.roles'))))))],
        ];
        if ($this->isMethod('POST')) {
            return $rules + ['name' => ['required', 'string', 'max:150'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'], 'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()]];
        }

        return $rules + ['status' => ['required', Rule::in(['active', 'suspended'])]];
    }
}

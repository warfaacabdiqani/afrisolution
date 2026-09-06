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
        $rules = ['tenant_id' => ['prohibited'], 'is_platform_admin' => ['prohibited'], 'role' => ['required', Rule::in(['owner', 'admin', 'staff'])]];
        if ($this->isMethod('POST')) {
            return $rules + ['name' => ['required', 'string', 'max:150'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'], 'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()]];
        }

        return $rules + ['status' => ['required', Rule::in(['active', 'suspended'])]];
    }
}

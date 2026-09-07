<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlatformSubscriptionIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_platform_admin;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['trial', 'active', 'cancelled'])],
            'plan_id' => ['nullable', 'integer', 'exists:plans,id'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

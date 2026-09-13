<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BusinessTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_platform_admin;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'alpha_dash:ascii', 'max:80', Rule::unique('business_types', 'slug')->ignore($this->route('business_type'))],
            'category' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:1000'],
            'icon' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'sort_order' => ['nullable', 'integer'],
        ];
    }
}

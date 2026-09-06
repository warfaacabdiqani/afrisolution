<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_platform_admin;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:150', Rule::unique('branches', 'name')->where('tenant_id', $this->route('tenant')->id)], 'tenant_id' => ['prohibited']];
    }
}

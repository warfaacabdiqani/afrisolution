<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_platform_admin;
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:100'], 'branch_limit' => ['required', 'integer', 'between:1,10000'], 'member_limit' => ['required', 'integer', 'between:1,100000'], 'trial_days' => ['required', 'integer', 'between:1,365'], 'tenant_id' => ['prohibited']];
    }
}

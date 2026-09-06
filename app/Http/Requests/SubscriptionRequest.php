<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_platform_admin;
    }

    public function rules(): array
    {
        return ['plan_id' => ['required', 'integer', 'exists:plans,id'], 'status' => ['required', Rule::in(['trial', 'active', 'cancelled'])], 'trial_ends_at' => ['required_if:status,trial', 'nullable', 'date', 'after:now'], 'tenant_id' => ['prohibited']];
    }
}

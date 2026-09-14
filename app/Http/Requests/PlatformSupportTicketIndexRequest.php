<?php

namespace App\Http\Requests;

use App\Support\SupportTicketOptions as Options;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlatformSupportTicketIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPlatformPermission('support_tickets.view');
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'business_type_id' => ['nullable', 'integer', 'exists:business_types,id'],
            'status' => ['nullable', Rule::in(Options::STATUSES)],
            'priority' => ['nullable', Rule::in(Options::PRIORITIES)],
            'category' => ['nullable', Rule::in(Options::CATEGORIES)],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

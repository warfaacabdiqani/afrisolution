<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BillingSourceRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return array_fill_keys(['tenant_id', 'branch_id', 'patient_id', 'salon_client_id', 'customer', 'customer_id', 'customer_type',
            'source_type', 'source_id', 'items', 'subtotal', 'discount', 'tax', 'tax_rate', 'total', 'paid', 'currency', 'number'], 'prohibited');
    }
}

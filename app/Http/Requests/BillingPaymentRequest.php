<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BillingPaymentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'amount' => 'required|numeric|min:0.01|max:9999999999|decimal:0,2',
            'method' => 'required|in:cash,card,mobile_money,bank_transfer',
            'reference' => 'nullable|string|max:255', 'idempotency_key' => 'required|string|max:80',
            'tenant_id' => 'prohibited', 'branch_id' => 'prohibited', 'invoice_id' => 'prohibited',
            'patient_id' => 'prohibited', 'salon_client_id' => 'prohibited', 'total' => 'prohibited', 'paid' => 'prohibited',
        ];
    }
}

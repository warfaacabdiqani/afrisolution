<?php

namespace App\Services\Billing;

use App\Services\ClinicSettingsService;

class BillingDocumentSnapshot
{
    /** Only scalar fields that appear on printed financial documents. */
    public function identity(array $context, string $branchName, string $customerType): array
    {
        $general = app(ClinicSettingsService::class)->section($context['clinic']->id, 'general');
        return [
            'business_name' => (string) $context['clinic']->name,
            'branch_name' => $branchName,
            'business_email' => (string) ($general['email'] ?? ''),
            'business_phone' => (string) ($general['phone'] ?? ''),
            'business_address' => (string) ($general['address'] ?? ''),
            'customer_label' => $customerType === 'salon_client' ? 'Client' : 'Patient',
        ];
    }
}

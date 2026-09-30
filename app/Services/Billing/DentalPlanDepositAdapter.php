<?php

namespace App\Services\Billing;

use App\Services\DentalPlanService;
use Illuminate\Validation\ValidationException;

class DentalPlanDepositAdapter implements DepositSource
{
    public function snapshot(array $context, int $id): array
    {
        abort_unless($context['business_type']['slug'] === 'dental', 403);
        $plan = app(DentalPlanService::class)->find($context, $id);
        if ($plan->status !== 'accepted') throw ValidationException::withMessages(['status' => 'Accept the treatment plan before collecting its deposit.']);
        $required = BillingMoney::cents($plan->deposit_required);
        if (!$required) throw ValidationException::withMessages(['deposit' => 'This treatment plan does not require a deposit.']);
        return [
            'tenant_id' => $plan->tenant_id, 'branch_id' => $plan->branch_id, 'source_id' => $plan->id,
            'customer_type' => 'patient', 'customer_id' => $plan->patient_id, 'currency' => $plan->currency,
            'discount' => '0.00', 'tax_rate' => '0.00',
            'items' => [['source_type' => 'dental_plan_deposit', 'source_id' => $plan->id,
                'description' => 'Treatment plan deposit - '.$plan->title, 'quantity' => 1,
                'unit_price' => BillingMoney::decimal($required)]],
            'expected_totals' => ['total' => BillingMoney::decimal($required)],
            'audit' => ['business_type' => 'dental', 'plan_id' => $plan->id, 'patient_id' => $plan->patient_id],
        ];
    }
}

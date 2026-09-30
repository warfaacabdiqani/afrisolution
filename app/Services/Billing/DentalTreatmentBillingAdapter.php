<?php
namespace App\Services\Billing;

use App\Models\{BillingDepositAllocation, BillingInvoice, DentalPlanItem};
use App\Services\DentalPlanService;
use Illuminate\Validation\ValidationException;

class DentalTreatmentBillingAdapter implements InvoiceSource
{
    public function snapshot(array $context, int $id): array
    {
        abort_unless($context['business_type']['slug'] === 'dental' && !empty($context['business_modules']['dental']), 403);
        $item = DentalPlanItem::findOrFail($id);
        $plan = app(DentalPlanService::class)->find($context, $item->plan_id);
        if ($item->status !== 'completed') throw ValidationException::withMessages(['treatment' => 'Only completed dental treatments can be invoiced.']);
        $deposit = BillingInvoice::where('source_type', 'dental_plan_deposit')->where('source_id', $plan->id)
            ->whereNull('voided_at')->first();
        $allocated = $deposit ? BillingMoney::cents(BillingDepositAllocation::where('deposit_invoice_id', $deposit->id)->sum('amount')) : 0;
        $gross = app(BillingMoney::class)->calculate([['description' => $item->procedure_name,
            'unit_price' => $item->unit_price, 'quantity' => $item->quantity]], '0.00', $plan->tax_rate);
        $credit = $deposit ? min(BillingMoney::cents($gross['totals']['total']), max(0, BillingMoney::cents($deposit->total) - $allocated)) : 0;
        return [
            'tenant_id' => $plan->tenant_id, 'branch_id' => $plan->branch_id, 'source_id' => $item->id,
            'customer_type' => 'patient', 'customer_id' => $plan->patient_id,
            'currency' => $plan->currency, 'discount' => '0.00', 'tax_rate' => $plan->tax_rate,
            'credit' => BillingMoney::decimal($credit), 'deposit_invoice_id' => $credit ? $deposit?->id : null,
            'items' => [['source_type' => 'dental_treatment', 'source_id' => $item->id,
                'description' => $item->procedure_name.($item->tooth ? ' — Tooth '.$item->tooth : ''),
                'quantity' => $item->quantity, 'unit_price' => $item->unit_price]],
            'audit' => ['plan_id' => $plan->id, 'patient_id' => $plan->patient_id],
        ];
    }
}

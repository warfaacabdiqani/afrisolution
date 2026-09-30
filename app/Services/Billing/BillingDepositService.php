<?php

namespace App\Services\Billing;

use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Services\BillingService;
use Illuminate\Validation\ValidationException;

class BillingDepositService
{
    public function required(string $mode, mixed $value, mixed $basis): string
    {
        $basisCents = BillingMoney::cents($basis ?? 0);
        if ($mode === 'none' || $basisCents === 0) return '0.00';
        $required = $mode === 'fixed'
            ? BillingMoney::cents($value ?? 0)
            : intdiv(($basisCents * BillingMoney::cents($value ?? 0)) + 5000, 10000);
        return BillingMoney::decimal(min($basisCents, $required));
    }

    public function collect(array $context, string $sourceType, int $sourceId, array $payment): BillingInvoice
    {
        $invoice = app(BillingService::class)->createFromSource($context, $sourceType, $sourceId);
        if (BillingPayment::where('idempotency_key', $payment['idempotency_key'])->exists()) {
            return app(BillingService::class)->payment($context, $invoice->id, $payment);
        }
        if (BillingMoney::cents($payment['amount']) > BillingMoney::cents(BillingMoney::balance($invoice))) {
            throw ValidationException::withMessages(['amount' => 'Payment cannot exceed the required deposit.']);
        }
        return app(BillingService::class)->payment($context, $invoice->id, $payment);
    }

    public function summary(?BillingInvoice $invoice, mixed $required): array
    {
        $requiredCents = BillingMoney::cents($required ?? 0);
        $invoiced = $invoice && !$invoice->voided_at ? BillingMoney::cents($invoice->total) : 0;
        $collected = $invoice ? BillingMoney::cents($invoice->paid) : 0;
        $refunded = $invoice ? abs(BillingMoney::signedCents((string) $invoice->payments()->where('type', 'refund')->sum('amount'))) : 0;
        return [
            'required' => BillingMoney::decimal($requiredCents), 'invoiced' => BillingMoney::decimal($invoiced),
            'collected' => BillingMoney::decimal($collected), 'refunded' => BillingMoney::decimal($refunded),
            'remaining' => BillingMoney::decimal(max(0, $requiredCents - $collected)),
            'status' => !$requiredCents ? 'not_required' : (!$invoice ? 'not_collected' : $invoice->status),
            'invoice_id' => $invoice?->id,
        ];
    }

    public function resolveCancellation(array $context, ?BillingInvoice $invoice, string $disposition, string $idempotencyKey): void
    {
        if (!$invoice || $invoice->voided_at) return;
        if (BillingMoney::cents($invoice->paid) === 0) {
            $invoice->update(['status' => 'void', 'voided_at' => now(), 'voided_by' => request()->user()->id]);
            return;
        }
        if (!in_array($disposition, ['refund', 'forfeit'], true)) {
            throw ValidationException::withMessages(['deposit_disposition' => 'Choose whether to refund or forfeit the collected deposit.']);
        }
        if ($disposition === 'refund') app(BillingService::class)->refundDepositPayments($context, $invoice, $idempotencyKey);
    }
}

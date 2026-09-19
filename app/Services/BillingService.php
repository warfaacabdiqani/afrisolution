<?php

namespace App\Services;

use App\Models\{BillingInvoice, BillingPayment, BillingReceipt, Branch};
use App\Services\Billing\{BillingCustomerResolver, BillingDocumentSnapshot, BillingLock, BillingMoney, InvoiceSource};
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** One financial ledger. Registered adapters resolve business workflow records. */
class BillingService
{
    public function visible(array $context)
    {
        abort_unless((int) $context['clinic']->id === app(TenantContext::class)->id(), 403);
        return BillingInvoice::whereIn('branch_id', $context['branches']->pluck('id'));
    }

    public function createFromSource(array $context, string $sourceType, int $sourceId): BillingInvoice
    {
        return DB::transaction(function () use ($context, $sourceType, $sourceId) {
            $tenant = app(BillingLock::class)->acquire((int) $context['clinic']->id);
            $adapterClass = config('billing.sources.'.$sourceType);
            abort_unless($adapterClass && is_a($adapterClass, InvoiceSource::class, true), 422, 'Unsupported billing source.');
            $snapshot = app($adapterClass)->snapshot($context, $sourceId);
            abort_unless((int) $snapshot['tenant_id'] === $tenant->id && (int) $snapshot['source_id'] === $sourceId, 422, 'Invalid billing source ownership.');
            $branch = Branch::whereIn('id', $context['branches']->pluck('id'))->findOrFail($snapshot['branch_id']);
            $existing = $this->visible($context)->where('source_type', $sourceType)->where('source_id', $sourceId)->first();
            if ($existing) return $existing->load(['branch', 'items', 'payments.receipt']);
            $customer = app(BillingCustomerResolver::class)->resolve($tenant, $snapshot['customer_type'], (int) $snapshot['customer_id'], $branch->id);

            $month = now($tenant->timezone)->startOfMonth()->utc();
            $nextMonth = now($tenant->timezone)->startOfMonth()->addMonth()->utc();
            $limit = $context['limits']['invoice_limit'] ?? null;
            if ($limit !== null && BillingInvoice::where('created_at', '>=', $month)->where('created_at', '<', $nextMonth)->count() >= $limit) {
                throw ValidationException::withMessages(['plan' => 'Your monthly invoice limit has been reached.']);
            }
            $calculation = app(BillingMoney::class)->calculate($snapshot['items'], $snapshot['discount'], $snapshot['tax_rate']);
            foreach ($snapshot['expected_totals'] ?? [] as $field => $value) {
                if (BillingMoney::cents($calculation['totals'][$field]) !== BillingMoney::cents($value)) {
                    throw ValidationException::withMessages(['source' => 'The charge snapshots do not reconcile with the source totals.']);
                }
            }
            abort_unless(preg_match('/^[A-Z]{3}$/D', $snapshot['currency']), 422, 'Invalid invoice currency.');
            $settings = app(ClinicSettingsService::class)->section($tenant->id, 'billing');
            do {
                $tenant->increment('billing_invoice_sequence');
                $number = $settings['invoice_prefix'].str_pad((string) $tenant->billing_invoice_sequence, (int) $settings['number_length'], '0', STR_PAD_LEFT);
            } while (BillingInvoice::where('number', $number)->exists());
            $invoice = BillingInvoice::create($customer + $calculation['totals'] + [
                'branch_id' => $branch->id, 'source_type' => $sourceType, 'source_id' => $sourceId,
                'number' => $number, 'currency' => $snapshot['currency'], 'paid' => '0.00',
                'status' => BillingMoney::cents($calculation['totals']['total']) === 0 ? 'paid' : 'unpaid',
                'issued_at' => now(), 'snapshot_version' => 2, 'created_by' => request()->user()->id,
                'document_snapshot' => app(BillingDocumentSnapshot::class)->identity($context, $branch->name, $snapshot['customer_type']),
            ]);
            $invoice->items()->createMany($calculation['items']);
            app(PlatformService::class)->audit(request()->user()->id, 'billing.invoice.created', 'tenant', $tenant->id,
                ['invoice_id' => $invoice->id, 'source_type' => $sourceType, 'source_id' => $sourceId] + ($snapshot['audit'] ?? []));
            return $invoice->load(['branch', 'items', 'payments.receipt']);
        }, 5);
    }

    public function payment(array $context, int $id, array $data): BillingInvoice
    {
        return DB::transaction(function () use ($context, $id, $data) {
            $tenant = app(BillingLock::class)->acquire((int) $context['clinic']->id);
            $invoice = $this->visible($context)->with('branch')->findOrFail($id);
            $amount = BillingMoney::cents($data['amount']);
            $duplicate = BillingPayment::where('idempotency_key', $data['idempotency_key'])->first();
            if ($duplicate) {
                abort_unless((int) $duplicate->invoice_id === $invoice->id && BillingMoney::cents($duplicate->amount) === $amount
                    && $duplicate->method === $data['method'] && $duplicate->reference === ($data['reference'] ?? null), 409, 'This payment request key was already used.');
                return $invoice->load(['branch', 'items', 'payments.receipt']);
            }
            if (!in_array($invoice->status, ['unpaid', 'partial'], true) || $invoice->voided_at) {
                throw ValidationException::withMessages(['invoice' => 'This invoice cannot receive payments.']);
            }
            $settings = app(ClinicSettingsService::class)->section($context['clinic']->id, 'billing');
            if (!in_array($data['method'], $settings['payment_methods'], true)) throw ValidationException::withMessages(['method' => 'This payment method is disabled.']);
            $balance = BillingMoney::cents($invoice->total) - BillingMoney::cents($invoice->paid);
            if ($amount > $balance || $amount <= 0) throw ValidationException::withMessages(['amount' => 'Payment must be positive and cannot exceed the outstanding balance.']);
            if (!$settings['partial_payments'] && $amount !== $balance) throw ValidationException::withMessages(['amount' => 'Partial payments are disabled.']);
            $payment = $invoice->payments()->create([
                'amount' => BillingMoney::decimal($amount), 'method' => $data['method'], 'reference' => $data['reference'] ?? null,
                'idempotency_key' => $data['idempotency_key'], 'recorded_by' => request()->user()->id, 'paid_at' => now(),
            ]);
            $previousPaid = BillingMoney::cents($invoice->paid);
            $paid = $previousPaid + $amount;
            $invoice->update(['paid' => BillingMoney::decimal($paid), 'status' => $paid === BillingMoney::cents($invoice->total) ? 'paid' : 'partial']);
            do {
                $tenant->increment('billing_receipt_sequence');
                $number = $settings['receipt_prefix'].str_pad((string) $tenant->billing_receipt_sequence, (int) $settings['number_length'], '0', STR_PAD_LEFT);
            } while (BillingReceipt::where('number', $number)->exists());
            $identity = app(BillingDocumentSnapshot::class)->identity($context, $invoice->branch->name,
                $invoice->salon_client_id ? 'salon_client' : 'patient');
            $receipt = $payment->receipt()->create([
                'invoice_id' => $invoice->id, 'number' => $number,
                'snapshot' => $identity + [
                    'invoice_number' => $invoice->number, 'customer_name' => $invoice->customer_name,
                    'currency' => $invoice->currency, 'payment_amount' => BillingMoney::decimal($amount),
                    'payment_method' => $payment->method, 'payment_reference' => $payment->reference,
                    'payment_at' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
                    'invoice_total' => BillingMoney::decimal(BillingMoney::cents($invoice->total)),
                    'previously_paid' => BillingMoney::decimal($previousPaid),
                    'balance_after' => BillingMoney::decimal(BillingMoney::cents($invoice->total) - $paid),
                    'recorded_by_name' => request()->user()->name,
                ],
            ]);
            app(PlatformService::class)->audit(request()->user()->id, 'billing.payment.recorded', 'tenant', $context['clinic']->id, ['invoice_id' => $id, 'amount' => $amount / 100, 'method' => $data['method']]);
            app(PlatformService::class)->audit(request()->user()->id, 'billing.receipt.created', 'tenant', $context['clinic']->id,
                ['invoice_id' => $id, 'payment_id' => $payment->id, 'receipt_id' => $receipt->id, 'receipt_number' => $number]);
            return $invoice->load(['branch', 'items', 'payments.receipt']);
        }, 5);
    }
}

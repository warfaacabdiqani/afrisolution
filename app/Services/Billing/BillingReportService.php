<?php

namespace App\Services\Billing;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BillingReportService
{
    public function summary(array $context, array $filters): array
    {
        $timezone = $context['clinic']->timezone;
        $today = now($timezone)->toDateString();
        $from = $filters['from'] ?? Carbon::parse($today, $timezone)->startOfMonth()->toDateString();
        $to = $filters['to'] ?? $today;
        if ($from > $to) throw \Illuminate\Validation\ValidationException::withMessages(['to' => 'The end date must be on or after the start date.']);
        $start = Carbon::createFromFormat('!Y-m-d', $from, $timezone)->utc()->format('Y-m-d H:i:s');
        $end = Carbon::createFromFormat('!Y-m-d', $to, $timezone)->addDay()->utc()->format('Y-m-d H:i:s');
        $branches = $filters['branch_id'] ?? null;
        $branchIds = $branches === null || $branches === 'all'
            ? $context['branches']->pluck('id')->all() : [(int) $branches];

        $invoiceScope = DB::table('billing_invoices')->where('billing_invoices.tenant_id', $context['clinic']->id)
            ->whereIn('billing_invoices.branch_id', $branchIds)->whereNull('billing_invoices.voided_at')
            ->whereRaw('COALESCE(billing_invoices.issued_at, billing_invoices.created_at) >= ?', [$start])
            ->whereRaw('COALESCE(billing_invoices.issued_at, billing_invoices.created_at) < ?', [$end]);
        $paymentScope = DB::table('billing_payments')->join('billing_invoices', function ($join) {
            $join->on('billing_invoices.id', '=', 'billing_payments.invoice_id')
                ->on('billing_invoices.tenant_id', '=', 'billing_payments.tenant_id');
        })->where('billing_payments.tenant_id', $context['clinic']->id)
            ->whereIn('billing_invoices.branch_id', $branchIds)
            ->where('billing_payments.paid_at', '>=', $start)->where('billing_payments.paid_at', '<', $end);

        $totals = (clone $invoiceScope)->select('billing_invoices.currency')
            ->selectRaw('COUNT(*) AS invoice_count, COALESCE(SUM(billing_invoices.total), 0) AS invoiced, COALESCE(SUM(billing_invoices.total - billing_invoices.paid), 0) AS outstanding')
            ->groupBy('billing_invoices.currency')->get();
        $statuses = (clone $invoiceScope)->select('billing_invoices.currency', 'billing_invoices.status')
            ->selectRaw('COUNT(*) AS count')->groupBy('billing_invoices.currency', 'billing_invoices.status')->get();
        $methods = (clone $paymentScope)->select('billing_invoices.currency', 'billing_payments.method')
            ->selectRaw('COUNT(*) AS count, COALESCE(SUM(billing_payments.amount), 0) AS collected')
            ->groupBy('billing_invoices.currency', 'billing_payments.method')->get();

        $currencies = [];
        foreach ($totals as $row) {
            $currencies[$row->currency] = ['currency' => $row->currency,
                'total_invoiced' => $this->money($row->invoiced), 'total_collected' => '0.00',
                'outstanding' => $this->money($row->outstanding), 'invoice_count' => (int) $row->invoice_count,
                'statuses' => ['paid' => 0, 'partial' => 0, 'unpaid' => 0], 'payment_methods' => []];
        }
        foreach ($statuses as $row) {
            $currencies[$row->currency]['statuses'][$row->status] = (int) $row->count;
        }
        foreach ($methods as $row) {
            $currencies[$row->currency] ??= ['currency' => $row->currency, 'total_invoiced' => '0.00',
                'total_collected' => '0.00', 'outstanding' => '0.00', 'invoice_count' => 0,
                'statuses' => ['paid' => 0, 'partial' => 0, 'unpaid' => 0], 'payment_methods' => []];
            $currency = &$currencies[$row->currency];
            $currency['total_collected'] = BillingMoney::decimal(BillingMoney::signedCents($currency['total_collected']) + BillingMoney::signedCents($row->collected));
            $currency['payment_methods'][] = ['method' => $row->method, 'amount' => $this->money($row->collected), 'count' => (int) $row->count];
            unset($currency);
        }
        ksort($currencies);
        $invoices = (clone $invoiceScope)->leftJoin('branches', function ($join) {
            $join->on('branches.id', '=', 'billing_invoices.branch_id')
                ->on('branches.tenant_id', '=', 'billing_invoices.tenant_id');
        })->select('billing_invoices.id', 'billing_invoices.number', 'billing_invoices.currency', 'billing_invoices.customer_name',
            'billing_invoices.patient_id', 'billing_invoices.salon_client_id', 'billing_invoices.status', 'billing_invoices.total',
            'billing_invoices.paid', 'billing_invoices.issued_at', 'billing_invoices.created_at', 'branches.name as branch_name')
            ->orderByDesc('billing_invoices.id')->paginate(50);
        $isSalon = ($context['business_type']['slug'] ?? null) === 'beauty-salon';
        $invoices->getCollection()->transform(fn ($invoice) => [
            'id' => $invoice->id, 'number' => $invoice->number, 'currency' => $invoice->currency,
            'customer_name' => $invoice->customer_name,
            'customer_label' => $invoice->salon_client_id || (!$invoice->patient_id && $isSalon) ? 'Client' : 'Patient',
            'branch_name' => $invoice->branch_name, 'status' => $invoice->status,
            'issued_at' => Carbon::parse($invoice->issued_at ?? $invoice->created_at, 'UTC')->setTimezone($timezone)->toDateString(),
            'total' => $this->money($invoice->total), 'paid' => $this->money($invoice->paid),
            'balance' => BillingMoney::decimal(BillingMoney::cents($invoice->total) - BillingMoney::cents($invoice->paid)),
        ]);
        return [
            'business_name' => $context['clinic']->name,
            'filters' => ['from' => $from, 'to' => $to, 'timezone' => $timezone,
                'branch_id' => $branches ?? 'all',
                'branch_name' => count($branchIds) === 1 ? $context['branches']->firstWhere('id', $branchIds[0])?->name : 'All authorized branches'],
            'currencies' => array_values($currencies), 'invoices' => $invoices,
        ];
    }

    private function money(mixed $value): string
    {
        return BillingMoney::decimal(BillingMoney::signedCents($value ?? '0'));
    }
}

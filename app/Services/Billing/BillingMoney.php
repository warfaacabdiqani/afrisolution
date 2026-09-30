<?php

namespace App\Services\Billing;

use Illuminate\Validation\ValidationException;

class BillingMoney
{
    public static function cents(mixed $value): int
    {
        $value = (string) $value;
        if (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', $value)) {
            throw ValidationException::withMessages(['amount' => 'Use a non-negative amount with at most two decimal places.']);
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }

    public static function signedCents(mixed $value): int
    {
        $value = (string) $value;
        $negative = str_starts_with($value, '-');
        if ($negative) $value = substr($value, 1);
        $cents = self::cents($value);
        return $negative ? -$cents : $cents;
    }

    public static function decimal(int $cents): string
    {
        return ($cents < 0 ? '-' : '').intdiv(abs($cents), 100).'.'.str_pad((string) (abs($cents) % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function balance($invoice): string
    {
        return self::decimal(self::cents($invoice->total) - self::cents($invoice->paid));
    }

    public function calculate(array $charges, mixed $discount, mixed $taxRate, mixed $postTaxCredit = '0.00'): array
    {
        if (!$charges) throw ValidationException::withMessages(['items' => 'An invoice requires at least one charge.']);
        $subtotal = 0;
        $items = [];
        foreach ($charges as $charge) {
            $quantity = filter_var($charge['quantity'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000000]]);
            if (!$quantity || trim($charge['description']) === '') throw ValidationException::withMessages(['items' => 'Invalid invoice charge.']);
            $unit = self::cents($charge['unit_price']);
            $amount = $unit * $quantity;
            $subtotal += $amount;
            if ($subtotal > 999999999999) throw ValidationException::withMessages(['items' => 'The invoice exceeds the supported amount.']);
            $items[] = ['source_type' => $charge['source_type'] ?? null, 'source_id' => $charge['source_id'] ?? null,
                'description' => $charge['description'], 'quantity' => $quantity, 'unit_price' => self::decimal($unit), 'amount' => self::decimal($amount)];
        }
        $discount = self::cents($discount);
        $rate = self::cents($taxRate);
        if ($discount > $subtotal || $rate > 10000) throw ValidationException::withMessages(['amount' => 'Invalid discount or tax rate.']);
        $tax = intdiv(($subtotal - $discount) * $rate + 5000, 10000);
        $credit = self::cents($postTaxCredit);
        $gross = $subtotal - $discount + $tax;
        if ($credit > $gross) throw ValidationException::withMessages(['credit' => 'Credit cannot exceed the invoice total.']);
        $total = $gross - $credit;
        if ($total > 999999999999) throw ValidationException::withMessages(['amount' => 'The invoice exceeds the supported amount.']);
        // Allocate by cumulative proportions; the final line receives the rounding remainder.
        $runningBase = $runningNet = $allocatedDiscount = $allocatedTax = 0;
        foreach ($items as &$item) {
            $base = self::cents($item['amount']);
            $runningBase += $base;
            $nextDiscount = $subtotal ? (int) round($discount * ($runningBase / $subtotal)) : 0;
            $lineDiscount = $nextDiscount - $allocatedDiscount;
            $runningNet += $base - $lineDiscount;
            $nextTax = intdiv($runningNet * $rate + 5000, 10000);
            $lineTax = $nextTax - $allocatedTax;
            $item += ['discount_amount' => self::decimal($lineDiscount), 'tax_rate' => self::decimal($rate),
                'tax_amount' => self::decimal($lineTax), 'line_total' => self::decimal($base - $lineDiscount + $lineTax)];
            $allocatedDiscount = $nextDiscount;
            $allocatedTax = $nextTax;
        }
        unset($item);
        if ($credit) $items[] = ['source_type' => 'billing_deposit', 'source_id' => null, 'kind' => 'credit',
            'description' => 'Deposit previously invoiced', 'quantity' => 1, 'unit_price' => self::decimal(-$credit),
            'amount' => self::decimal(-$credit), 'discount_amount' => '0.00', 'tax_rate' => '0.00',
            'tax_amount' => '0.00', 'line_total' => self::decimal(-$credit)];
        return ['items' => $items, 'totals' => ['subtotal' => self::decimal($subtotal), 'discount' => self::decimal($discount),
            'tax_rate' => self::decimal($rate), 'tax' => self::decimal($tax), 'credit' => self::decimal($credit), 'total' => self::decimal($total)]];
    }
}

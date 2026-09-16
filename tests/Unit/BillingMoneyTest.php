<?php

namespace Tests\Unit;

use App\Services\Billing\BillingMoney;
use PHPUnit\Framework\TestCase;

class BillingMoneyTest extends TestCase
{
    public function test_rounding_allocations_reconcile_even_for_tiny_and_fully_discounted_charges(): void
    {
        $calculator = new BillingMoney;
        foreach (['0.00', '0.01', '0.02', '0.03'] as $discount) {
            $result = $calculator->calculate(array_fill(0, 3, ['description' => 'Tiny charge', 'quantity' => 1, 'unit_price' => '0.01']), $discount, '50.00');
            $this->assertSame(BillingMoney::cents($result['totals']['total']), array_sum(array_map(fn ($line) => BillingMoney::cents($line['line_total']), $result['items'])));
            $this->assertSame(BillingMoney::cents($discount), array_sum(array_map(fn ($line) => BillingMoney::cents($line['discount_amount']), $result['items'])));
            $this->assertSame(BillingMoney::cents($result['totals']['tax']), array_sum(array_map(fn ($line) => BillingMoney::cents($line['tax_amount']), $result['items'])));
        }
        $this->assertSame('0.00', $result['totals']['total']);
    }

    public function test_large_supported_amounts_and_fractional_tax_are_exact(): void
    {
        $result = (new BillingMoney)->calculate([
            ['description' => 'Charge A', 'quantity' => 1, 'unit_price' => '4000000000.01'],
            ['description' => 'Charge B', 'quantity' => 1, 'unit_price' => '4000000000.02'],
        ], '0.01', '7.25');
        $this->assertSame('8000000000.03', $result['totals']['subtotal']);
        $this->assertSame('580000000.00', $result['totals']['tax']);
        $this->assertSame('8580000000.02', $result['totals']['total']);
        $this->assertSame(858000000002, array_sum(array_map(fn ($line) => BillingMoney::cents($line['line_total']), $result['items'])));
    }
}

<?php

namespace Tests\Unit;

use Modules\Integrations\Services\Payments\PaymentFeeCalculator;
use PHPUnit\Framework\TestCase;

class PaymentFeeCalculatorTest extends TestCase
{
    public function test_installment_adds_fee_percent(): void
    {
        $quote = (new PaymentFeeCalculator)->quote(100000, 10, 'installment', false);

        $this->assertSame(100000, $quote['base_amount']);
        $this->assertSame(10.0, $quote['fee_percent']);
        $this->assertSame(10000, $quote['fee_amount']);
        $this->assertSame(110000, $quote['total_amount']);
        $this->assertTrue($quote['fee_applied']);
    }

    public function test_cash_skips_fee_unless_flagged(): void
    {
        $calc = new PaymentFeeCalculator;

        $plain = $calc->quote(100000, 10, 'cash', false);
        $this->assertSame(0, $plain['fee_amount']);
        $this->assertSame(100000, $plain['total_amount']);
        $this->assertFalse($plain['fee_applied']);

        $withFee = $calc->quote(100000, 10, 'cash', true);
        $this->assertSame(10000, $withFee['fee_amount']);
        $this->assertSame(110000, $withFee['total_amount']);
        $this->assertTrue($withFee['fee_applied']);
    }

    public function test_rounds_half_up_and_ignores_negative_base(): void
    {
        $quote = (new PaymentFeeCalculator)->quote(-50, 2.5, 'installment', false);

        $this->assertSame(0, $quote['base_amount']);
        $this->assertSame(0, $quote['fee_amount']);
    }
}

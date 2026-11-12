<?php

namespace Tests\Unit;

use App\Support\Currency;
use App\Support\Money;
use PHPUnit\Framework\TestCase;

class CurrencyMoneyTest extends TestCase
{
    public function test_iranian_display_factors(): void
    {
        $this->assertSame(1.0, Currency::rialFactor('IRR'));
        $this->assertSame(10.0, Currency::rialFactor('IRT'));
        $this->assertSame(10000.0, Currency::rialFactor('IRHT'));
        $this->assertSame(1200000, Currency::toRialInt(120000, 'IRT'));
        $this->assertSame(120000.0, Currency::fromRial(1200000, 'IRT'));
        $this->assertSame(120.0, Currency::fromRial(1200000, 'IRHT'));
    }

    public function test_gateway_always_rial(): void
    {
        $this->assertSame(1200000, Currency::toGatewayRial(120000, 'IRT'));
        $this->assertSame(1200000, Currency::toGatewayRial(1200000, 'IRR'));
        $this->assertSame(1200000, Currency::toGatewayRial(120, 'IRHT'));
        $this->assertSame(1200000, Money::gatewayRialFromBase(1200000));
    }

    public function test_foreign_requires_fx(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Currency::toRial(10, 'USD');
    }

    public function test_foreign_with_fx(): void
    {
        $this->assertSame(5000000, Currency::toRialInt(10, 'USD', 500000));
        $this->assertSame(10.0, Currency::fromRial(5000000, 'USD', 500000));
        $this->assertSame(5000000, Currency::toGatewayRial(10, 'EUR', 500000));
    }

    public function test_normalize_aliases(): void
    {
        $this->assertSame('IRT', Currency::normalize('تومان'));
        $this->assertSame('IRR', Currency::normalize('ریال'));
        $this->assertSame('IRHT', Currency::normalize('هزارتومان'));
        $this->assertSame('USD', Currency::normalize('usd'));
    }

    public function test_labels(): void
    {
        $this->assertSame('تومان', Currency::label('IRT', 'fa'));
        $this->assertSame('Rial', Currency::label('IRR', 'en'));
        $this->assertSame('هزار تومان', Currency::label('IRHT', 'fa'));
        $this->assertSame('دلار', Currency::label('USD', 'fa'));
    }

    public function test_toman_store_multiplies_only_for_irr_gateways(): void
    {
        $this->assertSame(100000, Currency::toGatewayApiAmount(10000, 'IRT', 'zibal'));
        $this->assertSame(10000, Currency::toGatewayApiAmount(10000, 'IRT', 'zarinpal'));
        $this->assertSame(10000, Currency::toGatewayApiAmount(10000, 'IRT', 'nextpay'));
        $this->assertSame('IRR', Currency::apiUnitFor('zibal', 'IRT'));
        $this->assertSame('IRT', Currency::apiUnitFor('zarinpal', 'IRT'));
    }

    public function test_null_currency_defaults_to_toman_not_rial(): void
    {
        $this->assertSame(100000, Currency::toGatewayRial(10000, null));
    }

}

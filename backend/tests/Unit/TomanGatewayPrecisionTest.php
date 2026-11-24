<?php

namespace Tests\Unit;

use Modules\Integrations\Services\Payments\CommerceDrivers\AghayepardakhtDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\BitpayDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\EasypayDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\IranWalletDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\NextpayDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\ParspalDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\PaypingDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\Concerns\ConvertsFxAmount;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TomanGatewayPrecisionTest extends TestCase
{
    public function test_non_representable_amount_never_reaches_toman_gateways(): void
    {
        Http::fake();
        $drivers = [
            [new AghayepardakhtDriver, ['pin' => 'test', 'api_key' => 'test', 'api_token' => 'test', 'merchant_id' => 'test']],
            [new BitpayDriver, ['pin' => 'test', 'api_key' => 'test', 'api_token' => 'test', 'merchant_id' => 'test']],
            [new EasypayDriver, ['pin' => 'test', 'api_key' => 'test', 'api_token' => 'test', 'merchant_id' => 'test']],
            [new IranWalletDriver, ['pin' => 'test', 'api_key' => 'test', 'api_token' => 'test', 'merchant_id' => 'test']],
            [new NextpayDriver, ['pin' => 'test', 'api_key' => 'test', 'api_token' => 'test', 'merchant_id' => 'test']],
            [new ParspalDriver, ['pin' => 'test', 'api_key' => 'test', 'api_token' => 'test', 'merchant_id' => 'test']],
            [new PaypingDriver, ['pin' => 'test', 'api_key' => 'test', 'api_token' => 'test', 'merchant_id' => 'test']],
        ];

        foreach ($drivers as [$driver, $config]) {
            foreach ([0, 1, 101, -10] as $amount) {
                $result = $driver->request($config, $amount, 'https://example.local/callback', 'test');
                $this->assertFalse($result['ok'], $driver->code());
            }
        }

        Http::assertNothingSent();
    }
    public function test_fx_toman_conversion_is_exact(): void
    {
        $converter = new class
        {
            use ConvertsFxAmount;

            public function convert(int $amount, string $currency = 'IRT'): array
            {
                return $this->fxMinorAmount(['currency' => $currency], $amount);
            }
        };

        $this->assertFalse($converter->convert(101)['ok']);
        $this->assertSame(['ok' => true, 'amount' => 10, 'currency' => 'IRT'], $converter->convert(100));
        $this->assertFalse($converter->convert(0, 'IRR')['ok']);
        $this->assertFalse($converter->convert(-10, 'USD')['ok']);
        $this->assertSame(['ok' => true, 'amount' => 100, 'currency' => 'IRR'], $converter->convert(100, 'IRR'));
    }

}

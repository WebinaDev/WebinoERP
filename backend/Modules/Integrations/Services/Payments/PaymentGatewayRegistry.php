<?php

namespace Modules\Integrations\Services\Payments;

use Modules\Integrations\Services\Payments\CommerceDrivers\AghayepardakhtDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\AsanpardakhtDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\BinancePayDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\BitpayDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\CoingateDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\CoinpaymentsDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\CryptomusDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\EasypayDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\IdpayDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\IrankishDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\IranWalletDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\IrcryptopayDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\JibitDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\MellatDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\NextpayDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\NowpaymentsDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\OmidpayDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\ParsianDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\ParspalDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\PasargadDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\PayeerDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\PayfaDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\PayirDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\PaypalDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\PaypingDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\PaystarDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\PerfectMoneyDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\SadadDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\SaderatDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\SamanDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\SamankishDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\SepehrDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\ShepaDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\StripeDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\TaraDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\TomanDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\VandarDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\ZarinplusDriver;
use Modules\Integrations\Services\Payments\CommerceDrivers\ZibalDriver;
use Modules\Integrations\Services\Payments\Drivers\CommerceDriverAdapter;
use Modules\Integrations\Services\Payments\Drivers\DigipayDriver;
use Modules\Integrations\Services\Payments\Drivers\SnappPayDriver;
use Modules\Integrations\Services\Payments\Drivers\TorobPayDriver;
use Modules\Integrations\Services\Payments\Drivers\ZarinpalDriver;

class PaymentGatewayRegistry
{
    /** @var array<string, PaymentGatewayDriver> */
    private array $drivers = [];

    public function __construct(GatewayHttp $http)
    {
        foreach ([
            new ZarinpalDriver($http),
            new SnappPayDriver($http),
            new DigipayDriver($http),
            new TorobPayDriver($http),
        ] as $driver) {
            $this->drivers[$driver->code()] = $driver;
        }

        foreach ([
            new VandarDriver,
            new AghayepardakhtDriver,
            new BitpayDriver,
            new IdpayDriver,
            new NextpayDriver,
            new PaypingDriver,
            new PayirDriver,
            new ZibalDriver,
            new ShepaDriver,
            new PaystarDriver,
            new JibitDriver,
            new ParspalDriver,
            new PayfaDriver,
            new EasypayDriver,
            new ZarinplusDriver,
            new TomanDriver,
            new TaraDriver,
            new AsanpardakhtDriver,
            new PasargadDriver,
            new SepehrDriver,
            new SaderatDriver,
            new SamankishDriver,
            new IrankishDriver,
            new OmidpayDriver,
            new IranWalletDriver,
            new SamanDriver,
            new SadadDriver,
            new ParsianDriver,
            new MellatDriver,
            new StripeDriver,
            new PaypalDriver,
            new NowpaymentsDriver,
            new CryptomusDriver,
            new CoingateDriver,
            new PerfectMoneyDriver,
            new PayeerDriver,
            new CoinpaymentsDriver,
            new BinancePayDriver,
            new IrcryptopayDriver,
        ] as $inner) {
            $adapter = new CommerceDriverAdapter($inner);
            $this->drivers[$adapter->code()] = $adapter;
        }
    }

    public function get(string $code): PaymentGatewayDriver
    {
        if (! isset($this->drivers[$code])) {
            throw new PaymentException('درگاه ناشناخته است.', 'unknown_gateway');
        }

        return $this->drivers[$code];
    }

    /**
     * @return list<string>
     */
    public function codes(): array
    {
        return array_keys($this->drivers);
    }
}

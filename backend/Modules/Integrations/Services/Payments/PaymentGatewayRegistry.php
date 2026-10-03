<?php

namespace Modules\Integrations\Services\Payments;

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

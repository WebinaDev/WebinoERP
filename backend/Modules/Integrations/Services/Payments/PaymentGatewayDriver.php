<?php

namespace Modules\Integrations\Services\Payments;

use Modules\Integrations\Entities\PaymentIntent;

interface PaymentGatewayDriver
{
    public function code(): string;

    /**
     * @param  array<string, mixed>  $config
     */
    public function requestPayment(PaymentIntent $intent, array $config, string $callbackUrl): GatewayResult;

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $callback
     */
    public function verifyPayment(PaymentIntent $intent, array $config, array $callback): GatewayResult;

    /**
     * @param  array<string, mixed>  $config
     */
    public function settlePayment(PaymentIntent $intent, array $config): GatewayResult;

    /**
     * @param  array<string, mixed>  $config
     */
    public function cancelPayment(PaymentIntent $intent, array $config): GatewayResult;

    /**
     * @param  array<string, mixed>  $config
     */
    public function revertPayment(PaymentIntent $intent, array $config): GatewayResult;

    /**
     * @param  array<string, mixed>  $config
     * @return array{eligible:bool,title:string,description:string}
     */
    public function checkEligible(int $amount, array $config): array;

    /**
     * @param  array<string, mixed>  $config
     * @return array{ok:bool,message:string}
     */
    public function testConnection(array $config): array;
}

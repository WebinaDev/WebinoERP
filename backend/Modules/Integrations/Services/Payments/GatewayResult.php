<?php

namespace Modules\Integrations\Services\Payments;

class GatewayResult
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public bool $ok,
        public string $message = '',
        public string $authority = '',
        public string $redirectUrl = '',
        public string $providerRef = '',
        public array $payload = [],
        public bool $eligible = true,
        public string $eligibleTitle = '',
        public string $eligibleDescription = '',
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function success(
        string $message = '',
        string $authority = '',
        string $redirectUrl = '',
        string $providerRef = '',
        array $payload = [],
    ): self {
        return new self(true, $message, $authority, $redirectUrl, $providerRef, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function failure(string $message, array $payload = []): self
    {
        return new self(false, $message, '', '', '', $payload);
    }
}

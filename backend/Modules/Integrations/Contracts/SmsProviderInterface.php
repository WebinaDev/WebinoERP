<?php

namespace Modules\Integrations\Contracts;

interface SmsProviderInterface
{
    /**
     * @param  array<string, mixed>  $settings
     */
    public function send(string $to, string $message, array $settings = []): bool;
}

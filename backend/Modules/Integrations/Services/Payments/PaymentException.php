<?php

namespace Modules\Integrations\Services\Payments;

class PaymentException extends \RuntimeException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        string $message,
        public string $errorCode = 'payment_error',
        public int $status = 422,
        public array $details = [],
    ) {
        parent::__construct($message);
    }
}

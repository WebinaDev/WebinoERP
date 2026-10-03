<?php

namespace Modules\SiteBuilder\Exceptions;

use RuntimeException;
use Throwable;

class SiteImpersonationException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 422,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }
}

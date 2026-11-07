<?php

namespace Modules\Integrations\Services\Payments\CommerceDrivers;

/** Bank Saderat uses Sepehr IPG — same endpoints as SepehrDriver. */
class SaderatDriver extends SepehrDriver
{
    public function code(): string
    {
        return 'saderat';
    }
}

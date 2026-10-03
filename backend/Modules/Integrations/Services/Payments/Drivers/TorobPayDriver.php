<?php

namespace Modules\Integrations\Services\Payments\Drivers;

class TorobPayDriver extends SnappStyleDriver
{
    public function code(): string
    {
        return 'torobpay';
    }

    protected function defaultProductionBase(): string
    {
        return 'https://cpg.torobpay.com';
    }
}

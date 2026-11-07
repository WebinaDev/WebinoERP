<?php

namespace Modules\Integrations\Services\Payments\Drivers;

class SnappPayDriver extends SnappStyleDriver
{
    public function code(): string
    {
        return 'snapppay';
    }

    protected function defaultProductionBase(): string
    {
        return 'https://api.snapppay.ir';
    }
}

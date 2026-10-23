<?php

namespace Modules\Crm\Events\Customer;

class OrderPlaced extends CustomerActivityEvent
{
    public const MODULE = 'marketplace';

    public const TYPE = 'order.placed';

    public const SUBJECT = 'marketplace_order';

    public const TITLE = 'سفارش جدید';
}

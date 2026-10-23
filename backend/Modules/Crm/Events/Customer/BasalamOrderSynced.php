<?php

namespace Modules\Crm\Events\Customer;

class BasalamOrderSynced extends CustomerActivityEvent
{
    public const MODULE = 'basalam';

    public const TYPE = 'basalam.order';

    public const SUBJECT = 'basalam_order';

    public const TITLE = 'سفارش باسلام';
}

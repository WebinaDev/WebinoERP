<?php

namespace Modules\Crm\Events\Customer;

class PaymentRecorded extends CustomerActivityEvent
{
    public const MODULE = 'accounting';

    public const TYPE = 'payment.recorded';

    public const SUBJECT = 'payment';

    public const TITLE = 'ثبت پرداخت';
}

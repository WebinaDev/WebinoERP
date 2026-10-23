<?php

namespace Modules\Crm\Events\Customer;

class SmsSent extends CustomerActivityEvent
{
    public const MODULE = 'sms';

    public const TYPE = 'sms.sent';

    public const SUBJECT = 'crm_message';

    public const TITLE = 'ارسال پیامک';
}

<?php

namespace Modules\Crm\Events\Customer;

class InvoicePaid extends CustomerActivityEvent
{
    public const MODULE = 'sales';

    public const TYPE = 'invoice.paid';

    public const SUBJECT = 'sales_invoice';

    public const TITLE = 'پرداخت فاکتور';
}

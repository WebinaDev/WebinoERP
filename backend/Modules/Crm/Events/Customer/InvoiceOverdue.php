<?php

namespace Modules\Crm\Events\Customer;

class InvoiceOverdue extends CustomerActivityEvent
{
    public const MODULE = 'sales';

    public const TYPE = 'invoice.overdue';

    public const SUBJECT = 'sales_invoice';

    public const TITLE = 'فاکتور معوق';
}

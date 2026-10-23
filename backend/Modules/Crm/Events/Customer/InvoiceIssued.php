<?php

namespace Modules\Crm\Events\Customer;

class InvoiceIssued extends CustomerActivityEvent
{
    public const MODULE = 'sales';

    public const TYPE = 'invoice.issued';

    public const SUBJECT = 'sales_invoice';

    public const TITLE = 'صدور فاکتور';
}

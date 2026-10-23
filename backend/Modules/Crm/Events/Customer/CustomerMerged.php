<?php

namespace Modules\Crm\Events\Customer;

class CustomerMerged extends CustomerActivityEvent
{
    public const MODULE = 'crm';

    public const TYPE = 'customer.merged';

    public const SUBJECT = 'crm_account';

    public const TITLE = 'ادغام مشتری';
}

<?php

namespace Modules\Crm\Events\Customer;

class ContractCancelled extends CustomerActivityEvent
{
    public const MODULE = 'contracts';

    public const TYPE = 'contract.cancelled';

    public const SUBJECT = 'prj_contract';

    public const TITLE = 'لغو قرارداد';
}

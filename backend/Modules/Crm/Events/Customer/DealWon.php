<?php

namespace Modules\Crm\Events\Customer;

class DealWon extends CustomerActivityEvent
{
    public const MODULE = 'crm_deals';

    public const TYPE = 'deal.won';

    public const SUBJECT = 'crm_deal';

    public const TITLE = 'معامله برنده شد';
}

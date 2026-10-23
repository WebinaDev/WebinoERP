<?php

namespace Modules\Crm\Events\Customer;

class DealLost extends CustomerActivityEvent
{
    public const MODULE = 'crm_deals';

    public const TYPE = 'deal.lost';

    public const SUBJECT = 'crm_deal';

    public const TITLE = 'معامله از دست رفت';
}

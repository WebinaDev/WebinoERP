<?php

namespace Modules\Crm\Events\Customer;

class TicketOpened extends CustomerActivityEvent
{
    public const MODULE = 'tickets';

    public const TYPE = 'ticket.opened';

    public const SUBJECT = 'prj_ticket';

    public const TITLE = 'تیکت جدید';
}

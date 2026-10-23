<?php

namespace Modules\Crm\Events\Customer;

class TicketClosed extends CustomerActivityEvent
{
    public const MODULE = 'tickets';

    public const TYPE = 'ticket.closed';

    public const SUBJECT = 'prj_ticket';

    public const TITLE = 'بستن تیکت';
}

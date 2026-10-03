<?php

namespace Modules\Crm\Console;

use Illuminate\Console\Command;
use Modules\Crm\Http\Controllers\CrmExpansionController;
use Modules\Integrations\Services\NotificationFanout;

class DispatchContentRemindersCommand extends Command
{
    protected $signature = 'webino:content:remind';

    protected $description = 'Send due content calendar reminders';

    public function handle(CrmExpansionController $controller, NotificationFanout $fanout): int
    {
        $response = $controller->remindDue($fanout);
        $count = (int) data_get($response->getData(true), 'data.count', 0);
        $this->info('Reminders sent: '.$count);

        return self::SUCCESS;
    }
}

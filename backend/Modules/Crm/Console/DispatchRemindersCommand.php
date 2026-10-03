<?php

namespace Modules\Crm\Console;

use Illuminate\Console\Command;
use Modules\Crm\Http\Controllers\TimelineController;

class DispatchRemindersCommand extends Command
{
    protected $signature = 'crm:dispatch-reminders';

    protected $description = 'Notify owners of due CRM activity reminders';

    public function handle(TimelineController $timeline): int
    {
        $count = $timeline->dispatchDue();
        $this->info("Reminders sent: {$count}");

        return self::SUCCESS;
    }
}

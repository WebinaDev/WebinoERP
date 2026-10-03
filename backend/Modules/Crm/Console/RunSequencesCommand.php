<?php

namespace Modules\Crm\Console;

use Illuminate\Console\Command;
use Modules\Crm\Services\CrmSequenceRunner;

class RunSequencesCommand extends Command
{
    protected $signature = 'crm:run-sequences';

    protected $description = 'Send due CRM nurture sequence steps';

    public function handle(CrmSequenceRunner $runner): int
    {
        $count = $runner->runDue();
        $this->info("Sequence steps sent: {$count}");

        return self::SUCCESS;
    }
}

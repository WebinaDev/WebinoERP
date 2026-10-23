<?php

namespace Modules\Crm\Console;

use Illuminate\Console\Command;
use Modules\Crm\Services\Automations\AutomationRunner;

class RunCustomerAutomationsCommand extends Command
{
    protected $signature = 'crm:automations-run {--only=* : Rule keys to run} {--dry-run : Report matches without acting}';

    protected $description = 'Run enabled Customer 360 automations (renewal/installment reminders, debtor status, auto-suspend, churn risk, document expiry).';

    public function handle(AutomationRunner $runner): int
    {
        foreach ($runner->run($this->option('only') ?: null, (bool) $this->option('dry-run')) as $k => $s) {
            $this->line($k.': '.json_encode($s));
        }

        return self::SUCCESS;
    }
}

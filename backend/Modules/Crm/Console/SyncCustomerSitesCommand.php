<?php

namespace Modules\Crm\Console;

use Illuminate\Console\Command;
use Modules\Crm\Services\Hub\CustomerSiteSync;

class SyncCustomerSitesCommand extends Command
{
    protected $signature = 'crm:hub-sync-sites {--account= : Only this CRM account id} {--force : Ignore schedule/backoff}';

    protected $description = 'Collect usage metrics from customer sites and retry pending suspension syncs (with backoff).';

    public function handle(CustomerSiteSync $sync): int
    {
        $s = $sync->run($this->option('account') ? (int) $this->option('account') : null, (bool) $this->option('force'));
        $this->info(sprintf('usage ok=%d failed=%d | suspension ok=%d failed=%d | skipped(backoff/fresh)=%d',
            $s['usage_ok'], $s['usage_failed'], $s['suspension_ok'], $s['suspension_failed'], $s['skipped']));

        return self::SUCCESS;
    }
}

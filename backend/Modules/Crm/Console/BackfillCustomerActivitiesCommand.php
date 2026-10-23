<?php

namespace Modules\Crm\Console;

use Illuminate\Console\Command;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Services\Hub\CustomerActivityBackfill;

class BackfillCustomerActivitiesCommand extends Command
{
    protected $signature = 'crm:activities-backfill {--account=* : Only these account ids} {--chunk=200}';

    protected $description = 'Build crm_customer_activities (unified customer timeline) from existing module data. Idempotent.';

    public function handle(CustomerActivityBackfill $backfill): int
    {
        $q = CrmAccount::query()->orderBy('id');
        if ($ids = array_filter((array) $this->option('account'))) {
            $q->whereIn('id', $ids);
        }
        $accounts = 0;
        $rows = 0;
        $q->chunkById(max(1, (int) $this->option('chunk')), function ($chunk) use ($backfill, &$accounts, &$rows) {
            foreach ($chunk as $a) {
                $rows += $backfill->forAccount($a);
                $accounts++;
            }
        });
        $this->info("accounts: {$accounts}, new activities: {$rows}");

        return self::SUCCESS;
    }
}

<?php

namespace Modules\Integrations\Console;

use Illuminate\Console\Command;
use Modules\Integrations\Entities\CalendarAccount;
use Modules\Integrations\Services\CalendarSyncService;

class SyncCalendarsCommand extends Command
{
    protected $signature = 'webino:calendars:sync';

    protected $description = 'Pull and push Google and Outlook calendars';

    public function handle(CalendarSyncService $sync): int
    {
        $count = 0;
        CalendarAccount::query()->where('status', '!=', 'disabled')->orderBy('id')->each(function (CalendarAccount $account) use ($sync, &$count) {
            try {
                $sync->sync($account);
                $count++;
            } catch (\Throwable $e) {
                $account->update(['status' => 'error']);
                $this->warn($account->id.': '.$e->getMessage());
            }
        });
        $this->info('Synced '.$count.' calendars.');

        return self::SUCCESS;
    }
}

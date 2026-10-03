<?php

namespace Modules\Crm\Console;

use Illuminate\Console\Command;
use Modules\Crm\Entities\CrmEInvoice;
use Modules\Crm\Services\MoadianClient;

class RetryMoadianCommand extends Command
{
    protected $signature = 'webino:moadian:retry';

    protected $description = 'Retry Moadian e-invoice submissions that are waiting';

    public function handle(MoadianClient $client): int
    {
        $rows = CrmEInvoice::query()
            ->where('status', 'retry')
            ->where(function ($query) {
                $query->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', now());
            })
            ->orderBy('id')
            ->limit(50)
            ->get();
        foreach ($rows as $invoice) {
            $client->submit($invoice, false);
        }
        $this->info('moadian retry scanned '.$rows->count());

        return self::SUCCESS;
    }
}

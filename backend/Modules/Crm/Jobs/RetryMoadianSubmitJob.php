<?php

namespace Modules\Crm\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Crm\Entities\CrmEInvoice;
use Modules\Crm\Services\MoadianClient;

class RetryMoadianSubmitJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public int $invoiceId) {}

    public function handle(MoadianClient $client): void
    {
        $invoice = CrmEInvoice::query()->find($this->invoiceId);
        if (! $invoice || ! in_array($invoice->status, ['retry', 'queued'], true)) {
            return;
        }
        $client->submit($invoice, false);
    }
}

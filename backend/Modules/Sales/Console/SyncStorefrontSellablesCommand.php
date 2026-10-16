<?php

namespace Modules\Sales\Console;

use Illuminate\Console\Command;
use Modules\Sales\Services\Storefront\StorefrontSellables;

class SyncStorefrontSellablesCommand extends Command
{
    protected $signature = 'storefront:sync-sellables';

    protected $description = 'Create shop catalog items for builder product pages and academy courses (create-only).';

    public function handle(StorefrontSellables $sellables): int
    {
        $out = $sellables->sync();
        $this->info('created: '.implode(', ', $out['created']));
        $this->line('existing: '.count($out['existing']));

        return self::SUCCESS;
    }
}

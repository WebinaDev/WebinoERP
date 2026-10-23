<?php

namespace Modules\SiteBuilder\Console;

use Illuminate\Console\Command;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Modules\SiteBuilder\Services\SiteProvisionOrchestrator;
use Throwable;

/**
 * Scheduled every minute: bootstraps provisions stuck in ssl_pending so a site
 * becomes ready even when nobody keeps the Site Builder status page open.
 */
class AdvanceSslPendingCommand extends Command
{
    protected $signature = 'site-builder:advance-provisions {id? : Only this provision id}';

    protected $description = 'Health-check and bootstrap ssl_pending site provisions (with backoff).';

    public function handle(SiteProvisionOrchestrator $orchestrator): int
    {
        $query = WebinoSiteProvision::query()->where('status', WebinoSiteProvision::STATUS_SSL_PENDING)->orderBy('id');
        if ($this->argument('id')) {
            $query->whereKey((int) $this->argument('id'));
        }
        foreach ($query->limit(20)->get() as $provision) {
            try {
                $result = $orchestrator->advanceSslPending($provision);
            } catch (Throwable $e) {
                $result = 'error: '.$e->getMessage();
            }
            $this->line($provision->id.' '.$provision->slug.' '.$result);
        }

        return self::SUCCESS;
    }
}

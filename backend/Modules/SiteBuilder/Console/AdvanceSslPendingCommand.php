<?php

namespace Modules\SiteBuilder\Console;

use Illuminate\Console\Command;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Modules\SiteBuilder\Services\SiteProvisionOrchestrator;
use Throwable;

/**
 * Scheduled every minute: bootstraps provisions stuck in ssl_pending so a site
 * becomes ready even when nobody keeps the Site Builder status page open.
 * Also reconciles rows left in "provisioning" after a successful launch
 * (e.g. when ProvisionProgress::report() used to wipe unsaved status=ready).
 */
class AdvanceSslPendingCommand extends Command
{
    protected $signature = 'site-builder:advance-provisions {id? : Only this provision id}';

    protected $description = 'Health-check and bootstrap ssl_pending site provisions, and recover stuck provisioning rows (with backoff).';

    public function handle(SiteProvisionOrchestrator $orchestrator): int
    {
        $query = WebinoSiteProvision::query()
            ->whereIn('status', [
                WebinoSiteProvision::STATUS_SSL_PENDING,
                WebinoSiteProvision::STATUS_PROVISIONING,
            ])
            ->orderBy('id');
        if ($this->argument('id')) {
            $query->whereKey((int) $this->argument('id'));
        }
        foreach ($query->limit(20)->get() as $provision) {
            try {
                if ($provision->status === WebinoSiteProvision::STATUS_PROVISIONING) {
                    $result = $orchestrator->reconcileStuckProvisioning($provision);
                } else {
                    $result = $orchestrator->advanceSslPending($provision);
                }
            } catch (Throwable $e) {
                $result = 'error: '.$e->getMessage();
            }
            $this->line($provision->id.' '.$provision->slug.' '.$result);
        }

        return self::SUCCESS;
    }
}

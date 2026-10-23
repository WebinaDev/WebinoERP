<?php

namespace Modules\SiteBuilder\Console;

use Illuminate\Console\Command;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Modules\SiteBuilder\Services\SiteStatusService;
use Throwable;

/**
 * Suspends ready sites whose license (webino_site_provisions.license_id -> core_licenses.expires_at)
 * has expired, with reason license_expired, and re-activates sites that were suspended ONLY for
 * license_expired once the license is renewed. Manual suspensions are never lifted here.
 */
class EnforceLicenseSiteStatusCommand extends Command
{
    protected $signature = 'site-builder:enforce-license-status {--dry-run}';

    protected $description = 'Suspend sites with expired licenses and reactivate renewed ones.';

    public function handle(SiteStatusService $service): int
    {
        $now = now();
        $dry = (bool) $this->option('dry-run');
        $query = WebinoSiteProvision::query()
            ->where('status', WebinoSiteProvision::STATUS_READY)
            ->whereNotNull('license_id')
            ->with('license');

        foreach ($query->lazyById(100) as $p) {
            $license = $p->license;
            if (! $license) {
                continue;
            }
            $expired = $license->expires_at !== null && $license->expires_at->lte($now);
            $action = null;
            if ($expired && $p->site_status !== WebinoSiteProvision::SITE_SUSPENDED) {
                $action = 'suspend';
            } elseif (! $expired
                && $p->site_status === WebinoSiteProvision::SITE_SUSPENDED
                && $p->site_status_reason === WebinoSiteProvision::REASON_LICENSE_EXPIRED) {
                $action = 'reactivate';
            }
            if ($action === null) {
                continue;
            }
            if ($dry) {
                $this->line("{$p->id} {$p->slug} would {$action}");

                continue;
            }
            try {
                if ($action === 'suspend') {
                    $service->set($p, WebinoSiteProvision::SITE_SUSPENDED, WebinoSiteProvision::REASON_LICENSE_EXPIRED, null, null, 'license');
                } else {
                    $service->set($p, WebinoSiteProvision::SITE_ACTIVE, null, null, null, 'license');
                }
                $this->line("{$p->id} {$p->slug} {$action}: ok");
            } catch (Throwable $e) {
                $this->line("{$p->id} {$p->slug} {$action}: error ".$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}

<?php

namespace Modules\SiteBuilder\Console;

use Illuminate\Console\Command;
use Modules\Platform\Services\LocalSameVpsProvisioner;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Modules\SiteBuilder\Services\SiteProvisionOrchestrator;
use Throwable;

class ResyncCaddySnippetsCommand extends Command
{
    protected $signature = 'site-builder:resync-caddy {--reload : Reload Caddy after rewriting snippets}';

    protected $description = 'Rewrite Caddy snippets for all ready/ssl_pending local sites (survives update.sh git clean)';

    public function handle(LocalSameVpsProvisioner $local, SiteProvisionOrchestrator $orchestrator): int
    {
        $rows = WebinoSiteProvision::query()
            ->whereIn('status', [
                WebinoSiteProvision::STATUS_READY,
                WebinoSiteProvision::STATUS_SSL_PENDING,
                WebinoSiteProvision::STATUS_PROVISIONING,
            ])
            ->whereNotNull('domain')
            ->where('domain', '!=', '')
            ->orderBy('id')
            ->get();

        $written = 0;
        foreach ($rows as $row) {
            try {
                $local->ensureCaddySnippet($row);
                $this->line("wrote {$row->slug}.caddy → {$row->domain}");
                $written++;
            } catch (Throwable $e) {
                $this->error("{$row->slug}: ".$e->getMessage());
            }
        }

        if ($this->option('reload') || $written > 0) {
            try {
                $orchestrator->reloadCaddyProxy();
                $this->info('Caddy reload requested.');
            } catch (Throwable $e) {
                $this->warn('Caddy reload: '.$e->getMessage());
            }
        }

        $this->info("Resynced {$written}/{$rows->count()} site snippet(s).");

        return self::SUCCESS;
    }
}

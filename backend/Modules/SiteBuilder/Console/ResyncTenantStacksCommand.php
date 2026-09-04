<?php

namespace Modules\SiteBuilder\Console;

use Illuminate\Console\Command;
use Modules\Platform\Services\LocalSameVpsProvisioner;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Throwable;

class ResyncTenantStacksCommand extends Command
{
    protected $signature = 'site-builder:resync-stacks {--dry-run : List sites without rewriting compose or bringing stacks up}';

    protected $description = 'Rewrite tenant compose (webino_sites proxy net), compose up, and attach for ready/ssl_pending sites';

    public function handle(LocalSameVpsProvisioner $local): int
    {
        $rows = WebinoSiteProvision::query()
            ->whereIn('status', [
                WebinoSiteProvision::STATUS_READY,
                WebinoSiteProvision::STATUS_SSL_PENDING,
                WebinoSiteProvision::STATUS_PROVISIONING,
            ])
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            $this->info('No tenant sites to resync.');

            return self::SUCCESS;
        }

        $ok = 0;
        $failed = 0;
        foreach ($rows as $row) {
            if ($this->option('dry-run')) {
                $this->line("would resync {$row->slug} ({$row->domain}) status={$row->status}");

                continue;
            }

            try {
                $result = $local->resyncStack($row);
                if ($result['exit_code'] !== 0) {
                    $failed++;
                    $this->error("{$row->slug}: compose exit={$result['exit_code']} ".trim($result['stderr'] ?: $result['stdout']));

                    continue;
                }
                $ok++;
                $this->line("resync {$row->slug} ok");
                if (($result['attach_log'] ?? '') !== '') {
                    foreach (explode("\n", $result['attach_log']) as $line) {
                        if ($line !== '') {
                            $this->line('  '.$line);
                        }
                    }
                }
            } catch (Throwable $e) {
                $failed++;
                $this->error("{$row->slug}: ".$e->getMessage());
            }
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run: '.$rows->count().' site(s).');

            return self::SUCCESS;
        }

        $this->info("Resynced {$ok}/{$rows->count()} stack(s)".($failed > 0 ? ", {$failed} failed" : '').'.');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}

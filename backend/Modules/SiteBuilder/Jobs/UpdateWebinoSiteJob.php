<?php

namespace Modules\SiteBuilder\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Modules\SiteBuilder\Services\SiteProvisionOrchestrator;
use Throwable;

class UpdateWebinoSiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public function __construct(
        public int $provisionId,
        public string $target,
    ) {}

    public function handle(SiteProvisionOrchestrator $orchestrator): void
    {
        $provision = WebinoSiteProvision::query()->find($this->provisionId);
        if (! $provision) {
            return;
        }

        try {
            $orchestrator->runUpdate($provision, $this->target);
        } catch (Throwable $e) {
            $payload = $provision->wizard_payload ?? [];
            $payload['update'] = [
                'target' => $this->target,
                'status' => 'failed',
                'error' => $e->getMessage(),
                'finished_at' => now()->toIso8601String(),
            ];
            $provision->update(['wizard_payload' => $payload, 'error_log' => $e->getMessage()]);
        }
    }
}

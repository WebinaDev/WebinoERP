<?php

namespace Modules\SiteBuilder\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Modules\SiteBuilder\Services\SiteProvisionOrchestrator;
use Modules\SiteBuilder\Support\ProvisionProgress;
use Throwable;

class ProvisionWebinoSiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 2400;

    public function __construct(public int $provisionId) {}

    public function handle(SiteProvisionOrchestrator $orchestrator): void
    {
        $provision = WebinoSiteProvision::query()->find($this->provisionId);
        if (! $provision) {
            return;
        }

        if ($provision->status === WebinoSiteProvision::STATUS_CANCELLED) {
            return;
        }

        ProvisionProgress::report($provision, ProvisionProgress::PHASE_QUEUED);

        try {
            $orchestrator->launch($provision);
        } catch (Throwable $e) {
            if (str_contains($e->getMessage(), 'platform.provision_cancelled')) {
                ProvisionProgress::report($provision, ProvisionProgress::PHASE_CANCELLED);

                return;
            }
            throw $e;
        }
    }
}

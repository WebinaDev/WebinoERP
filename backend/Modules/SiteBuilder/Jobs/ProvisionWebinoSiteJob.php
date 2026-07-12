<?php

namespace Modules\SiteBuilder\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Modules\SiteBuilder\Services\SiteProvisionOrchestrator;

class ProvisionWebinoSiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(public int $provisionId) {}

    public function handle(SiteProvisionOrchestrator $orchestrator): void
    {
        $provision = WebinoSiteProvision::query()->find($this->provisionId);
        if (! $provision) {
            return;
        }

        $orchestrator->launch($provision);
    }
}

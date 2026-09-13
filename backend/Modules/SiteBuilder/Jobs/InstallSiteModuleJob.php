<?php

namespace Modules\SiteBuilder\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Modules\SiteBuilder\Services\ModuleInstallOrchestrator;
use Throwable;

class InstallSiteModuleJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(
        public readonly int $siteProvisionId,
        public readonly string $moduleSlug,
    ) {}

    public function handle(ModuleInstallOrchestrator $orchestrator): void
    {
        $site = WebinoSiteProvision::query()->findOrFail($this->siteProvisionId);
        $orchestrator->run($site, $this->moduleSlug);
    }

    public function failed(?Throwable $e): void
    {
        // Status is updated inside orchestrator on failure; this is a safety net.
        report($e);
    }
}

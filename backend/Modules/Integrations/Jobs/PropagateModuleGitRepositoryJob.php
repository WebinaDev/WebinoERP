<?php

namespace Modules\Integrations\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Core\Jobs\InvalidateAllLicenseCheckCachesJob;

/**
 * After module_git_sources changes (webhook or admin UI), refresh license/Dashboard caches asynchronously.
 */
class PropagateModuleGitRepositoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $moduleSlug) {}

    public function handle(): void
    {
        InvalidateAllLicenseCheckCachesJob::dispatchSync();
    }
}

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

    /** One attempt. A second try overlaps docker builds; the UI retries explicitly. */
    public int $tries = 1;

    public function __construct(public int $provisionId) {}

    /**
     * Push the build onto a real queue.
     *
     * `dispatch()->afterResponse()` calls `dispatchSync()`, which forces the
     * sync connection and runs this job before `php artisan serve` finishes
     * the request. An exception then (for example DecryptException while
     * reading the hosting webhook secret) makes the built-in server answer
     * HTTP 500, so the wizard stays on draft and Redis never receives the job.
     */
    public static function enqueue(int $provisionId): void
    {
        $connection = config('queue.default');
        if (! is_string($connection) || $connection === '' || $connection === 'sync') {
            $connection = 'redis';
        }

        static::dispatch($provisionId)->onConnection($connection);
    }

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

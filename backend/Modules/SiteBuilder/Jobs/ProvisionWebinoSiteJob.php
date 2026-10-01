<?php

namespace Modules\SiteBuilder\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
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
     * Push the build onto a real queue. Never throws into the HTTP request.
     *
     * `dispatch()->afterResponse()` forces the sync connection and runs this
     * job inside `php artisan serve`, which rewrites the response to HTTP 500
     * when the job throws. A redis push that throws (missing driver, connection
     * refused) does the same if it escapes this method.
     *
     * Returns true when the job was accepted by redis or, if redis is missing
     * or down, by the database queue. The failure is logged either way.
     */
    public static function enqueue(int $provisionId): bool
    {
        $connection = static::queueConnectionName();
        if ($connection === 'redis') {
            static::ensureRedisQueueConnection();
        }

        try {
            static::push($provisionId, $connection);

            return true;
        } catch (Throwable $e) {
            Log::error('Site launch could not be queued.', [
                'provision_id' => $provisionId,
                'connection' => $connection,
                'queue_default' => config('queue.default'),
                'error' => $e->getMessage(),
            ]);
        }

        if ($connection === 'database') {
            return false;
        }

        try {
            static::ensureDatabaseQueueConnection();
            static::push($provisionId, 'database');
            Log::error('QUEUE_CONNECTION redis is unavailable; site launch was queued on the database fallback.', [
                'provision_id' => $provisionId,
                'queue_default' => config('queue.default'),
            ]);

            return true;
        } catch (Throwable $e) {
            Log::error('Site launch database queue fallback failed.', [
                'provision_id' => $provisionId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private static function queueConnectionName(): string
    {
        $connection = config('queue.default');
        if (! is_string($connection) || $connection === '' || $connection === 'sync') {
            return 'redis';
        }

        return $connection;
    }

    /**
     * AppServiceProvider may set only retry_after when config/queue.php did
     * not define a redis driver. Fill the driver so the push can reach Redis.
     */
    private static function ensureRedisQueueConnection(): void
    {
        $redis = config('queue.connections.redis');
        if (is_array($redis) && filled($redis['driver'] ?? null)) {
            return;
        }

        config(['queue.connections.redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => 2500,
            'block_for' => null,
            'after_commit' => false,
        ]]);
    }

    private static function ensureDatabaseQueueConnection(): void
    {
        $existing = config('queue.connections.database');
        if (is_array($existing) && ($existing['driver'] ?? null) === 'database') {
            if ((int) ($existing['retry_after'] ?? 0) < 2500) {
                config(['queue.connections.database.retry_after' => 2500]);
            }

            return;
        }

        config(['queue.connections.database' => [
            'driver' => 'database',
            'connection' => config('database.default'),
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 2500,
            'after_commit' => false,
        ]]);
    }

    private static function push(int $provisionId, string $connection): void
    {
        $job = (new static($provisionId))->onConnection($connection);
        Bus::dispatch($job);
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

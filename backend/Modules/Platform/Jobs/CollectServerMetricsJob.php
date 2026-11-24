<?php

namespace Modules\Platform\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Platform\Entities\PlatformServer;
use Modules\Platform\Services\PlatformServerMetricsCollector;
use Throwable;

class CollectServerMetricsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ?int $serverId = null) {}

    public function handle(PlatformServerMetricsCollector $collector): void
    {
        $query = PlatformServer::query();

        if ($this->serverId) {
            $query->where('id', $this->serverId);
        } else {
            // Collect for all reachable / ready / localhost servers
            $query->where(function ($q) {
                $q->where('is_localhost', true)
                    ->orWhereIn('status', ['ready', 'reachable', 'validating']);
            });
        }

        $servers = $query->get();

        foreach ($servers as $server) {
            try {
                $collector->collect($server);
            } catch (Throwable $e) {
                Log::warning("Failed to collect metrics for server {$server->id} ({$server->name}): " . $e->getMessage());
            }
        }
    }
}

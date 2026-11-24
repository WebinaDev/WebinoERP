<?php

namespace Modules\Platform\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Platform\Entities\PlatformServer;
use Modules\Platform\Services\PlatformServiceHealthCollector;
use Throwable;

class CollectServiceHealthJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ?int $serverId = null) {}

    public function handle(PlatformServiceHealthCollector $collector): void
    {
        $query = PlatformServer::query();

        if ($this->serverId) {
            $query->where('id', $this->serverId);
        } else {
            $query->where(function ($q) {
                $q->where('is_localhost', true)
                    ->orWhereIn('status', ['ready', 'reachable', 'validating']);
            });
        }

        foreach ($query->get() as $server) {
            try {
                $collector->collect($server);
            } catch (Throwable $e) {
                Log::warning("CollectServiceHealthJob failed for server {$server->id}: " . $e->getMessage());
            }
        }
    }
}

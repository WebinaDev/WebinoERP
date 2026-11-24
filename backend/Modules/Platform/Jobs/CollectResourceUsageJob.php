<?php

namespace Modules\Platform\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Modules\Platform\Services\PlatformResourceUsageCollector;
use Throwable;

class CollectResourceUsageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ?int $resourceId = null) {}

    public function handle(PlatformResourceUsageCollector $collector): void
    {
        try {
            if ($this->resourceId) {
                $resource = \Modules\Platform\Entities\PlatformResource::query()->find($this->resourceId);
                if ($resource) {
                    $collector->collectResource($resource);
                }
            } else {
                $collector->collectAll();
            }
        } catch (Throwable $e) {
            Log::warning('CollectResourceUsageJob error: ' . $e->getMessage());
        }
    }
}

<?php

namespace Modules\Integrations\Jobs;

use App\Support\ModuleMonitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Integrations\Entities\ChatBridge;
use Modules\Integrations\Entities\InboundEvent;
use Modules\Integrations\Services\ChatBridgeService;

class ProcessInboundChatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public int $eventId) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [15, 60, 180, 600, 1800];
    }

    public function handle(ChatBridgeService $chat): void
    {
        $event = InboundEvent::query()->find($this->eventId);
        if (! $event || $event->status === 'done') {
            return;
        }
        $bridge = $event->bridge_id ? ChatBridge::query()->find($event->bridge_id) : null;
        if (! $bridge) {
            $event->update([
                'status' => 'failed',
                'attempts' => $event->attempts + 1,
                'last_error' => 'bridge_missing',
            ]);

            return;
        }
        try {
            $chat->ingest($bridge, (array) $event->payload);
            $event->update([
                'status' => 'done',
                'processed_at' => now(),
                'last_error' => null,
            ]);
            ModuleMonitor::hook('integrations', 'webhook_processed', [
                'event_id' => $event->id,
                'provider' => $event->provider,
            ]);
        } catch (\Throwable $e) {
            $attempts = $event->attempts + 1;
            $event->update([
                'status' => 'failed',
                'attempts' => $attempts,
                'last_error' => $e->getMessage(),
                'available_at' => now()->addSeconds($this->backoff()[min($attempts - 1, 4)]),
            ]);
            ModuleMonitor::hook('integrations', 'webhook_failed', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}

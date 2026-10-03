<?php

namespace Modules\Integrations\Console;

use Illuminate\Console\Command;
use Modules\Integrations\Entities\InboundEvent;
use Modules\Integrations\Jobs\ProcessInboundChatJob;

class RetryInboundWebhooksCommand extends Command
{
    protected $signature = 'webino:webhooks:retry';

    protected $description = 'Retry queued or failed Slack, Bale, and Telegram webhook events';

    public function handle(): int
    {
        $events = InboundEvent::query()
            ->whereIn('status', ['failed', 'queued'])
            ->where('attempts', '<', 5)
            ->where(function ($query) {
                $query->whereNull('available_at')->orWhere('available_at', '<=', now());
            })
            ->orderBy('id')
            ->limit(100)
            ->get();
        foreach ($events as $event) {
            if ($event->status === 'queued' && $event->created_at && $event->created_at->gt(now()->subSeconds(20))) {
                continue;
            }
            try {
                ProcessInboundChatJob::dispatchSync($event->id);
            } catch (\Throwable $e) {
                $this->warn('event '.$event->id.' '.$e->getMessage());
            }
        }
        $this->info('webhook retry scanned '.$events->count());

        return self::SUCCESS;
    }
}

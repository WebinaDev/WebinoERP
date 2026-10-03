<?php

namespace Modules\Crm\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Crm\Entities\ContentItem;
use Modules\Crm\Services\SocialPublishService;

class PublishContentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public int $itemId) {}

    public function handle(SocialPublishService $publisher): void
    {
        $item = ContentItem::query()->find($this->itemId);
        if (! $item || $item->publish_status === 'published') {
            return;
        }
        $publisher->publish($item);
    }
}

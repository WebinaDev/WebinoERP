<?php

namespace Modules\Crm\Console;

use Illuminate\Console\Command;
use Modules\Crm\Entities\ContentItem;
use Modules\Crm\Services\SocialPublishService;

class PublishDueContentCommand extends Command
{
    protected $signature = 'webino:content:publish';

    protected $description = 'Publish due Instagram and LinkedIn calendar items';

    public function handle(SocialPublishService $publisher): int
    {
        $items = ContentItem::query()
            ->whereIn('publish_status', ['idle', 'retry', 'scheduled'])
            ->where(function ($query) {
                $query->where('status', 'scheduled')
                    ->orWhere('publish_status', 'retry');
            })
            ->where(function ($query) {
                $query->whereNull('publish_start')->orWhere('publish_start', '<=', now());
            })
            ->orderBy('id')
            ->limit(30)
            ->get();
        $count = 0;
        foreach ($items as $item) {
            $network = (string) $item->calendar()->value('network');
            if (! in_array($network, ['instagram', 'linkedin'], true)) {
                continue;
            }
            $publisher->publish($item);
            $count++;
        }
        $this->info('content publish scanned '.$count);

        return self::SUCCESS;
    }
}

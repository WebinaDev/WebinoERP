<?php

namespace Modules\SiteBuilder\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\SiteBuilder\Services\SiteAnnouncementDispatcher;

class DeliverSiteAnnouncementJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(public int $announcementId) {}

    public function handle(SiteAnnouncementDispatcher $dispatcher): void
    {
        $dispatcher->deliver($this->announcementId);
    }
}

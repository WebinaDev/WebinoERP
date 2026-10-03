<?php

namespace Modules\SiteBuilder\Console;

use Illuminate\Console\Command;
use Modules\SiteBuilder\Entities\WebinoSiteAnnouncement;
use Modules\SiteBuilder\Services\SiteAnnouncementDispatcher;

class PushSiteAnnouncementsCommand extends Command
{
    protected $signature = 'site-builder:push-announcements {id? : Announcement id. Omit to push every published notice.}';

    protected $description = 'Push site-admin announcements to tenant dashboards (or revoke archived ones).';

    public function handle(SiteAnnouncementDispatcher $dispatcher): int
    {
        $id = $this->argument('id');
        $ids = $id
            ? [(int) $id]
            : WebinoSiteAnnouncement::query()
                ->where('status', WebinoSiteAnnouncement::STATUS_PUBLISHED)
                ->pluck('id')
                ->all();

        foreach ($ids as $announcementId) {
            $dispatcher->deliver((int) $announcementId);
            $this->line('pushed '.$announcementId);
        }

        return self::SUCCESS;
    }
}

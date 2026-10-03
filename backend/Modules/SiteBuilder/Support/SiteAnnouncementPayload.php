<?php

namespace Modules\SiteBuilder\Support;

use Modules\SiteBuilder\Entities\WebinoSiteAnnouncement;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;

/**
 * Body for POST /api/v1/integrations/erp/announcements.
 * Shape matches WebinoDashboard docs/erp-tenant-announcements.md.
 * `read` is per user on the tenant and is not sent.
 */
final class SiteAnnouncementPayload
{
    public const PATH = 'integrations/erp/announcements';

    /**
     * @return array{id: int, title: string, body: string, created_at: string, audience: string, tenant_domain: string}
     */
    public static function item(WebinoSiteAnnouncement $announcement, WebinoSiteProvision $site): array
    {
        $title = trim((string) $announcement->title_fa);
        if ($title === '') {
            $title = trim((string) $announcement->title_en);
        }
        $body = trim((string) $announcement->body_fa);
        if ($body === '') {
            $body = trim((string) $announcement->body_en);
        }

        $audience = (string) ($announcement->inbox_audience ?: 'admins');
        if (! in_array($audience, WebinoSiteAnnouncement::INBOX_AUDIENCES, true)) {
            $audience = 'admins';
        }

        $created = $announcement->published_at ?? $announcement->created_at ?? now();

        return [
            'id' => (int) $announcement->id,
            'title' => $title,
            'body' => $body,
            'created_at' => $created->toIso8601String(),
            'audience' => $audience,
            'tenant_domain' => strtolower(trim((string) $site->domain)),
        ];
    }
}

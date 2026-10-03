<?php

namespace Modules\SiteBuilder\Support;

use Modules\SiteBuilder\Entities\WebinoSiteAnnouncement;

/**
 * JSON body pushed to each tenant at POST /api/v1/provision/announcements.
 * HMAC covers this object with JSON_UNESCAPED_UNICODE, same as other provision calls.
 */
final class SiteAnnouncementPayload
{
    public const VERSION = 1;

    public const PATH = 'provision/announcements';

    /**
     * @return array<string, mixed>
     */
    public static function upsert(WebinoSiteAnnouncement $announcement): array
    {
        return [
            'version' => self::VERSION,
            'announcement' => [
                'external_key' => $announcement->externalKey(),
                'action' => 'upsert',
                'level' => $announcement->level,
                'title_fa' => $announcement->title_fa,
                'title_en' => $announcement->title_en,
                'body_fa' => $announcement->body_fa,
                'body_en' => $announcement->body_en,
                'published_at' => optional($announcement->published_at)?->toIso8601String(),
                'expires_at' => optional($announcement->expires_at)?->toIso8601String(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function revoke(WebinoSiteAnnouncement $announcement): array
    {
        return [
            'version' => self::VERSION,
            'announcement' => [
                'external_key' => $announcement->externalKey(),
                'action' => 'revoke',
            ],
        ];
    }
}

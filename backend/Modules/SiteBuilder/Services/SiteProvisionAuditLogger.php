<?php

namespace Modules\SiteBuilder\Services;

use Modules\Core\Entities\CoreInfraAuditLog;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;

class SiteProvisionAuditLogger
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function log(?int $userId, string $action, WebinoSiteProvision $provision, array $payload = []): void
    {
        try {
            CoreInfraAuditLog::query()->create([
                'user_id' => $userId,
                'channel' => 'site_builder',
                'action' => $action,
                'subject_type' => WebinoSiteProvision::class,
                'subject_id' => (string) $provision->id,
                'payload' => array_merge([
                    'slug' => $provision->slug,
                    'domain' => $provision->domain,
                    'status' => $provision->status,
                ], $payload),
            ]);
        } catch (\Throwable) {
            /* audit table may be absent in partial installs */
        }
    }
}

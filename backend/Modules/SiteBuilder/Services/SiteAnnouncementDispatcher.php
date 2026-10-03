<?php

namespace Modules\SiteBuilder\Services;

use Illuminate\Support\Collection;
use Modules\SiteBuilder\Entities\WebinoSiteAnnouncement;
use Modules\SiteBuilder\Entities\WebinoSiteAnnouncementDelivery;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Modules\SiteBuilder\Jobs\DeliverSiteAnnouncementJob;
use Modules\SiteBuilder\Support\SiteAnnouncementPayload;
use Throwable;

class SiteAnnouncementDispatcher
{
    public function __construct(
        private readonly SiteAnnouncementAudience $audience,
        private readonly TenantAnnouncementClient $tenants,
    ) {}

    public function publish(WebinoSiteAnnouncement $announcement): WebinoSiteAnnouncement
    {
        if ($announcement->published_at === null) {
            $announcement->published_at = now();
        }
        $announcement->status = WebinoSiteAnnouncement::STATUS_PUBLISHED;
        $announcement->save();

        $this->syncTargets($announcement);
        DeliverSiteAnnouncementJob::dispatchSync($announcement->id);

        return $announcement->fresh(['deliveries.site']) ?? $announcement;
    }

    public function archive(WebinoSiteAnnouncement $announcement): WebinoSiteAnnouncement
    {
        $announcement->status = WebinoSiteAnnouncement::STATUS_ARCHIVED;
        $announcement->save();
        DeliverSiteAnnouncementJob::dispatchSync($announcement->id);

        return $announcement->fresh(['deliveries.site']) ?? $announcement;
    }

    public function retry(WebinoSiteAnnouncement $announcement): WebinoSiteAnnouncement
    {
        $this->syncTargets($announcement);
        DeliverSiteAnnouncementJob::dispatchSync($announcement->id);

        return $announcement->fresh(['deliveries.site']) ?? $announcement;
    }

    public function deliver(int $announcementId): void
    {
        $announcement = WebinoSiteAnnouncement::query()->find($announcementId);
        if (! $announcement) {
            return;
        }

        $this->syncTargets($announcement);
        $announcement->load('deliveries.site');

        foreach ($announcement->deliveries as $delivery) {
            $this->pushOne($announcement, $delivery);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function payloadsForSite(WebinoSiteProvision $site): array
    {
        $this->ensureDeliveriesForSite($site);

        $deliveries = WebinoSiteAnnouncementDelivery::query()
            ->where('site_provision_id', $site->id)
            ->with('announcement')
            ->orderBy('id')
            ->get();

        $payloads = [];
        foreach ($deliveries as $delivery) {
            $announcement = $delivery->announcement;
            if (! $announcement || $announcement->status === WebinoSiteAnnouncement::STATUS_DRAFT) {
                continue;
            }
            if ($this->shouldRevoke($announcement, $site)) {
                $payloads[] = SiteAnnouncementPayload::revoke($announcement);
            } else {
                $payloads[] = SiteAnnouncementPayload::upsert($announcement);
            }
        }

        return $payloads;
    }

    public function recordReceipt(WebinoSiteProvision $site, string $externalKey, string $event): ?WebinoSiteAnnouncementDelivery
    {
        if (! preg_match('/^erp-site-announcement:(\d+)$/', $externalKey, $match)) {
            return null;
        }
        $announcement = WebinoSiteAnnouncement::query()->find((int) $match[1]);
        if (! $announcement) {
            return null;
        }

        $delivery = WebinoSiteAnnouncementDelivery::query()->firstOrCreate(
            [
                'announcement_id' => $announcement->id,
                'site_provision_id' => $site->id,
            ],
            ['status' => WebinoSiteAnnouncementDelivery::STATUS_PENDING]
        );

        if ($event === 'read' && $delivery->read_at === null) {
            $delivery->read_at = now();
        }
        if ($event === 'dismissed') {
            $delivery->dismissed_at = $delivery->dismissed_at ?? now();
            $delivery->read_at = $delivery->read_at ?? now();
        }
        if ($event === 'delivered' && $delivery->delivered_at === null) {
            $delivery->status = WebinoSiteAnnouncementDelivery::STATUS_DELIVERED;
            $delivery->delivered_at = now();
        }
        $delivery->save();

        return $delivery;
    }

    private function syncTargets(WebinoSiteAnnouncement $announcement): void
    {
        foreach ($this->desiredSites($announcement) as $site) {
            WebinoSiteAnnouncementDelivery::query()->firstOrCreate(
                [
                    'announcement_id' => $announcement->id,
                    'site_provision_id' => $site->id,
                ],
                ['status' => WebinoSiteAnnouncementDelivery::STATUS_PENDING]
            );
        }
    }

    private function ensureDeliveriesForSite(WebinoSiteProvision $site): void
    {
        $published = WebinoSiteAnnouncement::query()
            ->where('status', WebinoSiteAnnouncement::STATUS_PUBLISHED)
            ->orderBy('id')
            ->get();

        foreach ($published as $announcement) {
            if ($announcement->isExpired() || ! $this->audience->matches($announcement, $site)) {
                continue;
            }
            WebinoSiteAnnouncementDelivery::query()->firstOrCreate(
                [
                    'announcement_id' => $announcement->id,
                    'site_provision_id' => $site->id,
                ],
                ['status' => WebinoSiteAnnouncementDelivery::STATUS_PENDING]
            );
        }
    }

    /**
     * @return Collection<int, WebinoSiteProvision>
     */
    private function desiredSites(WebinoSiteAnnouncement $announcement): Collection
    {
        if ($announcement->status !== WebinoSiteAnnouncement::STATUS_PUBLISHED || $announcement->isExpired()) {
            return collect();
        }

        return $this->audience->matchingSites($announcement);
    }

    private function shouldRevoke(WebinoSiteAnnouncement $announcement, WebinoSiteProvision $site): bool
    {
        return $announcement->status !== WebinoSiteAnnouncement::STATUS_PUBLISHED
            || $announcement->isExpired()
            || ! $this->audience->matches($announcement, $site);
    }

    private function pushOne(WebinoSiteAnnouncement $announcement, WebinoSiteAnnouncementDelivery $delivery): void
    {
        $site = $delivery->site;
        if (! $site) {
            return;
        }

        $revoke = $this->shouldRevoke($announcement, $site);
        if ($revoke && $delivery->delivered_at === null && $delivery->status !== WebinoSiteAnnouncementDelivery::STATUS_DELIVERED) {
            $delivery->status = WebinoSiteAnnouncementDelivery::STATUS_REVOKED;
            $delivery->last_error = null;
            $delivery->save();

            return;
        }

        if (! $this->canPush($site)) {
            if (! $revoke) {
                $delivery->status = WebinoSiteAnnouncementDelivery::STATUS_PENDING;
                $delivery->last_error = 'سایت هنوز آماده دریافت اطلاعیه نیست.';
                $delivery->save();
            }

            return;
        }

        try {
            $payload = $revoke
                ? SiteAnnouncementPayload::revoke($announcement)
                : SiteAnnouncementPayload::upsert($announcement);
            $this->tenants->push($site, $payload);
            $delivery->attempts = (int) $delivery->attempts + 1;
            $delivery->last_error = null;
            if ($revoke) {
                $delivery->status = WebinoSiteAnnouncementDelivery::STATUS_REVOKED;
            } else {
                $delivery->status = WebinoSiteAnnouncementDelivery::STATUS_DELIVERED;
                $delivery->delivered_at = now();
            }
            $delivery->save();
        } catch (Throwable $e) {
            $delivery->attempts = (int) $delivery->attempts + 1;
            $delivery->status = WebinoSiteAnnouncementDelivery::STATUS_FAILED;
            $delivery->last_error = mb_substr($e->getMessage() ?: 'push failed', 0, 2000);
            $delivery->save();
        }
    }

    private function canPush(WebinoSiteProvision $site): bool
    {
        if (trim((string) $site->domain) === '' || trim((string) $site->provision_token) === '') {
            return false;
        }

        return in_array($site->status, [
            WebinoSiteProvision::STATUS_READY,
            WebinoSiteProvision::STATUS_SSL_PENDING,
        ], true);
    }
}

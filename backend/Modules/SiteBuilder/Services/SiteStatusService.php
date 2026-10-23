<?php

namespace Modules\SiteBuilder\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Throwable;

/**
 * Platform-side owner of a tenant site's public state (active / coming_soon / suspended).
 * Every change is pushed to the tenant, mirrored locally and audit-logged.
 */
class SiteStatusService
{
    public function __construct(
        private readonly SiteStatusClient $client,
        private readonly SiteProvisionAuditLogger $audit,
    ) {}

    /**
     * @return array<string, mixed> remote response
     */
    public function set(
        WebinoSiteProvision $provision,
        string $status,
        ?string $reason = null,
        ?CarbonInterface $launchAt = null,
        ?int $userId = null,
        string $source = 'manual',
    ): array {
        $license = $provision->license()->first();
        $payload = [
            'status' => $status,
            'reason' => $status === WebinoSiteProvision::SITE_ACTIVE ? null : $reason,
            'launch_at' => $status === WebinoSiteProvision::SITE_COMING_SOON ? $launchAt?->toIso8601String() : null,
            'expires_at' => $license?->expires_at?->toIso8601String(),
            'renew_url' => config('site_builder.site_status_renew_url') ?: null,
            'support_url' => config('site_builder.site_status_support_url') ?: null,
        ];
        $previous = $provision->site_status;

        try {
            $remote = $this->client->put($provision, $payload);
        } catch (Throwable $e) {
            $provision->update(['site_status_sync_error' => mb_substr($e->getMessage(), 0, 1000)]);
            $this->audit->log($userId, 'site_status.failed', $provision, ['target' => $status, 'source' => $source, 'error' => mb_substr($e->getMessage(), 0, 300)]);
            throw $e;
        }

        $provision->update([
            'site_status' => $remote['status'] ?? $status,
            'site_status_reason' => $remote['reason'] ?? $payload['reason'],
            'site_launch_at' => $this->date($remote['launch_at'] ?? $payload['launch_at']),
            'site_status_updated_by' => $remote['updated_by'] ?? 'platform',
            'site_status_synced_at' => now(),
            'site_status_sync_error' => null,
        ]);
        $this->audit->log($userId, 'site_status.changed', $provision->fresh(), [
            'from' => $previous, 'to' => $status, 'reason' => $payload['reason'],
            'launch_at' => $payload['launch_at'], 'source' => $source,
        ]);

        return $remote;
    }

    /**
     * Reads the tenant's live state and mirrors it locally (the site admin may have toggled it).
     *
     * @return array<string, mixed>
     */
    public function refresh(WebinoSiteProvision $provision): array
    {
        try {
            $remote = $this->client->get($provision);
        } catch (Throwable $e) {
            $provision->update(['site_status_sync_error' => mb_substr($e->getMessage(), 0, 1000)]);
            throw $e;
        }
        if (in_array($remote['status'] ?? null, WebinoSiteProvision::SITE_STATUSES, true)) {
            $provision->update([
                'site_status' => $remote['status'],
                'site_status_reason' => $remote['reason'],
                'site_launch_at' => $this->date($remote['launch_at']),
                'site_status_updated_by' => $remote['updated_by'],
                'site_status_synced_at' => now(),
                'site_status_sync_error' => null,
            ]);
        }

        return $remote;
    }

    /** New sites start as coming soon; never overrides a status that was already set. Non-fatal. */
    public function markNewSiteComingSoon(WebinoSiteProvision $provision): void
    {
        if ($provision->site_status !== null) {
            return;
        }
        try {
            $this->set($provision, WebinoSiteProvision::SITE_COMING_SOON, null, null, $provision->created_by, 'provision');
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** @return array<string, mixed> */
    public function snapshot(WebinoSiteProvision $provision): array
    {
        return [
            'status' => $provision->site_status,
            'reason' => $provision->site_status_reason,
            'launch_at' => $provision->site_launch_at?->toIso8601String(),
            'updated_by' => $provision->site_status_updated_by,
            'synced_at' => $provision->site_status_synced_at?->toIso8601String(),
            'sync_error' => $provision->site_status_sync_error,
        ];
    }

    private function date(mixed $v): ?Carbon
    {
        if (! is_string($v) || $v === '') {
            return null;
        }
        try {
            return Carbon::parse($v);
        } catch (Throwable) {
            return null;
        }
    }
}

<?php

namespace Modules\Platform\Services;

use Illuminate\Support\Facades\Log;
use Modules\Core\Entities\CoreInfraAuditLog;
use Modules\Platform\Entities\PlatformAlertEvent;
use Modules\Platform\Entities\PlatformAlertRule;
use Modules\Platform\Entities\PlatformMetricSnapshot;
use Modules\Platform\Entities\PlatformResource;
use Modules\Platform\Entities\PlatformResourceQuota;
use RuntimeException;
use Throwable;

class PlatformQuotaEnforcer
{
    public function __construct(
        protected DockerRemoteService $docker
    ) {}

    /**
     * Check if resource deployment is allowed under quota policy.
     * Throws RuntimeException if blocked by hard quota throttle or suspension.
     */
    public function ensureDeployAllowed(PlatformResource $resource): void
    {
        // 1. If resource is marked as suspended, deny deployment immediately
        if ($resource->status === 'suspended' || ($resource->settings['is_suspended'] ?? false)) {
            throw new RuntimeException('استقرار متوقف شد: این منبع به دلیل عبور از سقف مجاز منابع در حالت تعلیق (Suspended) قرار دارد.');
        }

        // 2. Check quota policy
        $quota = PlatformResourceQuota::query()
            ->where('resource_id', $resource->id)
            ->where('is_enabled', true)
            ->first();

        if (! $quota) {
            return;
        }

        // Fetch latest usage
        $latest = PlatformMetricSnapshot::query()
            ->where('resource_id', $resource->id)
            ->where('scope', 'resource')
            ->orderBy('collected_at', 'desc')
            ->get()
            ->groupBy('metric_key')
            ->map(fn ($g) => (float) $g->first()->value);

        $diskMb = $latest->get('disk.used', 0.0) / (1024 * 1024);
        $ramMb = $latest->get('mem.used', 0.0) / (1024 * 1024);
        $cpu = $latest->get('cpu.usage', 0.0);

        $isHardBreached = false;
        $reason = '';

        if ($quota->disk_mb_hard && $diskMb >= $quota->disk_mb_hard) {
            $isHardBreached = true;
            $reason = "مصرف دیسک ({$diskMb} MB) از سقف مجاز سخت ({$quota->disk_mb_hard} MB) عبور کرده است.";
        } elseif ($quota->ram_mb_hard && $ramMb >= $quota->ram_mb_hard) {
            $isHardBreached = true;
            $reason = "مصرف حافظه ({$ramMb} MB) از سقف مجاز سخت ({$quota->ram_mb_hard} MB) عبور کرده است.";
        } elseif ($quota->cpu_percent_hard && $cpu >= $quota->cpu_percent_hard) {
            $isHardBreached = true;
            $reason = "مصرف پردازنده ({$cpu}%) از سقف مجاز سخت ({$quota->cpu_percent_hard}%) عبور کرده است.";
        }

        if ($isHardBreached) {
            // Log audit
            $this->logAction($resource, 'deploy_blocked', [
                'enforce_action' => $quota->enforce_action,
                'reason' => $reason,
            ]);

            if ($quota->enforce_action === 'throttle' || $quota->enforce_action === 'suspend') {
                throw new RuntimeException("استقرار مسدود شد (Throttle Enforcement): {$reason}");
            }
        }
    }

    /**
     * Enforce suspension on a resource when hard quota is breached with action=suspend.
     */
    public function suspendResource(PlatformResource $resource, string $reason): void
    {
        $settings = is_array($resource->settings) ? $resource->settings : [];
        $settings['is_suspended'] = true;
        $settings['suspend_reason'] = $reason;
        $settings['suspended_at'] = now()->toIso8601String();

        $resource->update([
            'status' => 'suspended',
            'settings' => $settings,
        ]);

        $this->logAction($resource, 'resource_suspended', [
            'reason' => $reason,
        ]);

        // Safely stop the container if server is reachable
        try {
            $server = $resource->server()->first();
            if ($server) {
                $this->docker->containerAction($server, $resource->name, 'stop');
            }
        } catch (Throwable $e) {
            Log::info("Could not stop container {$resource->name} during quota suspension: " . $e->getMessage());
        }

        // Raise critical audit alert
        $rule = PlatformAlertRule::query()->firstOrCreate(
            ['name' => 'Resource Quota Suspended'],
            [
                'scope_type' => 'resource',
                'metric_key' => 'quota.suspended',
                'operator' => 'gte',
                'threshold' => 1,
                'severity' => 'critical',
                'duration_seconds' => 0,
                'is_enabled' => true,
            ]
        );

        PlatformAlertEvent::query()->create([
            'rule_id' => $rule->id,
            'server_id' => $resource->server_id,
            'resource_id' => $resource->id,
            'status' => 'open',
            'severity' => 'critical',
            'message' => "منبع {$resource->name} به علت نقض سقف سخت تعلیق شد: {$reason}",
            'metric_value' => 1,
            'opened_at' => now(),
            'meta' => ['action' => 'suspend', 'reason' => $reason],
        ]);
    }

    protected function logAction(PlatformResource $resource, string $action, array $payload): void
    {
        try {
            if (class_exists(CoreInfraAuditLog::class)) {
                CoreInfraAuditLog::query()->create([
                    'user_id' => auth()->id(),
                    'channel' => 'platform_quota',
                    'action' => $action,
                    'subject_type' => PlatformResource::class,
                    'subject_id' => $resource->id,
                    'payload' => $payload,
                ]);
            }
        } catch (Throwable) {
            // Fail open for audit
        }
    }
}

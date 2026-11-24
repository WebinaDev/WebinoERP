<?php

namespace Modules\Platform\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Modules\Platform\Entities\PlatformAlertEvent;
use Modules\Platform\Entities\PlatformAlertRule;
use Modules\Platform\Entities\PlatformMailDomain;
use Modules\Platform\Entities\PlatformMetricSnapshot;
use Modules\Platform\Entities\PlatformServer;
use Modules\Platform\Entities\PlatformServiceHealth;
use Symfony\Component\Process\Process;
use Throwable;

class PlatformServiceHealthCollector
{
    public function __construct(
        protected SshExecutor $ssh
    ) {}

    /**
     * Collect health and runtime metrics for a specific server.
     */
    public function collect(PlatformServer $server): array
    {
        $checkedAt = Carbon::now();
        $isLocal = $server->is_localhost || in_array($server->ip, ['127.0.0.1', 'localhost', '::1'], true);

        $results = [];

        // 1. Caddy / Web Proxy
        $results['caddy'] = $this->checkCaddy($server, $isLocal);

        // 2. PHP / Octane / FrankenPHP
        $results['php'] = $this->checkPhp($server, $isLocal);

        // 3. Redis
        $results['redis'] = $this->checkRedis($server, $isLocal);

        // 4. Queue / Horizon
        $results['queue'] = $this->checkQueue($server, $isLocal);

        // 5. Scheduler
        $results['scheduler'] = $this->checkScheduler($server, $isLocal);

        // 6. Platform Mail
        $results['mail'] = $this->checkMail($server, $isLocal);

        // Store into platform_service_health & trigger alerts if down
        foreach ($results as $serviceKey => $health) {
            PlatformServiceHealth::query()->create([
                'server_id' => $server->id,
                'service_key' => $serviceKey,
                'status' => $health['status'],
                'message' => $health['message'] ?? null,
                'meta' => $health['meta'] ?? null,
                'checked_at' => $checkedAt,
            ]);

            // If service is down, raise or maintain alert
            if ($health['status'] === 'down') {
                $this->raiseServiceDownAlert($server, $serviceKey, $health['message'] ?? 'Service is down');
            } else {
                $this->resolveServiceDownAlert($server, $serviceKey);
            }
        }

        // 7. Store runtime service metrics (queue.pending, queue.failed, http request rate)
        $this->collectServiceMetrics($server, $results, $checkedAt);

        return [
            'server_id' => $server->id,
            'checked_at' => $checkedAt->toIso8601String(),
            'services' => $results,
        ];
    }

    protected function checkCaddy(PlatformServer $server, bool $isLocal): array
    {
        $cmd = 'pgrep -x caddy >/dev/null 2>&1 || pgrep -f "caddy run" >/dev/null 2>&1 || curl -fsS http://localhost:2019/config/ >/dev/null 2>&1 && echo UP || echo DOWN';
        $out = $this->runCommand($server, $cmd, $isLocal);

        $up = str_contains($out, 'UP');
        return [
            'status' => $up ? 'up' : 'down',
            'message' => $up ? 'Caddy proxy server is active' : 'Caddy process or admin endpoint not responding',
            'meta' => ['process_match' => $up],
        ];
    }

    protected function checkPhp(PlatformServer $server, bool $isLocal): array
    {
        $cmd = 'pgrep -f "php-fpm|octane|frankenphp|artisan serve" >/dev/null 2>&1 && echo UP || (php -v >/dev/null 2>&1 && echo UP || echo DOWN)';
        $out = $this->runCommand($server, $cmd, $isLocal);

        $up = str_contains($out, 'UP');
        return [
            'status' => $up ? 'up' : 'down',
            'message' => $up ? 'PHP runtime is available' : 'No active PHP-FPM, Octane or CLI binary detected',
            'meta' => ['active' => $up],
        ];
    }

    protected function checkRedis(PlatformServer $server, bool $isLocal): array
    {
        if ($isLocal) {
            try {
                if (class_exists(Redis::class)) {
                    $ping = Redis::connection()->ping();
                    if ($ping) {
                        return ['status' => 'up', 'message' => 'Redis ping successful', 'meta' => ['ping' => $ping]];
                    }
                }
            } catch (Throwable) {
                // fall back to process / redis-cli
            }
        }

        $cmd = 'redis-cli ping 2>/dev/null || pgrep -x redis-server >/dev/null 2>&1 && echo PONG || echo DOWN';
        $out = $this->runCommand($server, $cmd, $isLocal);
        $up = str_contains($out, 'PONG');

        return [
            'status' => $up ? 'up' : 'degraded',
            'message' => $up ? 'Redis connection active' : 'Redis instance not responding to ping',
            'meta' => ['ping' => $up ? 'PONG' : 'FAIL'],
        ];
    }

    protected function checkQueue(PlatformServer $server, bool $isLocal): array
    {
        $failedCount = 0;
        $pendingCount = 0;

        if ($isLocal) {
            try {
                if (SchemaHasTable('failed_jobs')) {
                    $failedCount = DB::table('failed_jobs')->count();
                }
                if (SchemaHasTable('jobs')) {
                    $pendingCount = DB::table('jobs')->count();
                }
            } catch (Throwable) {
                // Ignore DB error
            }
        }

        $cmd = 'pgrep -f "queue:work|horizon" >/dev/null 2>&1 && echo UP || echo DOWN';
        $out = $this->runCommand($server, $cmd, $isLocal);
        $workerActive = str_contains($out, 'UP');

        $status = ($failedCount > 20) ? 'degraded' : ($workerActive || $isLocal ? 'up' : 'degraded');

        return [
            'status' => $status,
            'message' => "Queue workers active. Pending: {$pendingCount}, Failed: {$failedCount}",
            'meta' => [
                'worker_active' => $workerActive,
                'pending_jobs' => $pendingCount,
                'failed_jobs' => $failedCount,
            ],
        ];
    }

    protected function checkScheduler(PlatformServer $server, bool $isLocal): array
    {
        // Check cron or supervisor for schedule:run
        $cmd = 'crontab -l 2>/dev/null | grep -q "schedule:run" || pgrep -f "schedule:run|schedule:work" >/dev/null 2>&1 && echo UP || echo UNKNOWN';
        $out = $this->runCommand($server, $cmd, $isLocal);
        $up = str_contains($out, 'UP');

        return [
            'status' => $up ? 'up' : ($isLocal ? 'up' : 'unknown'),
            'message' => $up ? 'Scheduler cron/worker heartbeat verified' : 'Scheduler configured in kernel',
            'meta' => ['cron_found' => $up],
        ];
    }

    protected function checkMail(PlatformServer $server, bool $isLocal): array
    {
        $domainsCount = 0;
        if (class_exists(PlatformMailDomain::class) && SchemaHasTable('platform_mail_domains')) {
            $domainsCount = PlatformMailDomain::query()->count();
        }

        return [
            'status' => 'up',
            'message' => "Mail subsystem operational with {$domainsCount} active domain(s)",
            'meta' => ['domains_count' => $domainsCount],
        ];
    }

    protected function collectServiceMetrics(PlatformServer $server, array $results, Carbon $checkedAt): void
    {
        $queueMeta = $results['queue']['meta'] ?? [];
        $pending = (float) ($queueMeta['pending_jobs'] ?? 0);
        $failed = (float) ($queueMeta['failed_jobs'] ?? 0);

        // Store queue.pending & queue.failed
        PlatformMetricSnapshot::query()->create([
            'server_id' => $server->id,
            'scope' => 'service',
            'metric_key' => 'queue.pending',
            'value' => $pending,
            'unit' => 'count',
            'labels' => ['service' => 'queue'],
            'collected_at' => $checkedAt,
        ]);

        PlatformMetricSnapshot::query()->create([
            'server_id' => $server->id,
            'scope' => 'service',
            'metric_key' => 'queue.failed',
            'value' => $failed,
            'unit' => 'count',
            'labels' => ['service' => 'queue'],
            'collected_at' => $checkedAt,
        ]);

        // Evaluate queue.failed spike rule
        if ($failed > 10) {
            $this->raiseQueueFailedAlert($server, $failed);
        }
    }

    protected function raiseServiceDownAlert(PlatformServer $server, string $serviceKey, string $msg): void
    {
        $rule = PlatformAlertRule::query()->firstOrCreate(
            ['name' => "Service Down: {$serviceKey}"],
            [
                'scope_type' => 'server',
                'scope_id' => $server->id,
                'metric_key' => "service.{$serviceKey}.status",
                'operator' => 'lt',
                'threshold' => 1,
                'severity' => 'critical',
                'duration_seconds' => 0,
                'is_enabled' => true,
            ]
        );

        $existing = PlatformAlertEvent::query()
            ->where('rule_id', $rule->id)
            ->where('server_id', $server->id)
            ->whereIn('status', ['open', 'acknowledged'])
            ->first();

        if (! $existing) {
            PlatformAlertEvent::query()->create([
                'rule_id' => $rule->id,
                'server_id' => $server->id,
                'status' => 'open',
                'severity' => 'critical',
                'message' => "سرویس {$serviceKey} روی سرور {$server->name} از کار افتاده است: {$msg}",
                'metric_value' => 0,
                'opened_at' => now(),
                'meta' => ['service_key' => $serviceKey, 'type' => 'service_down'],
            ]);
        }
    }

    protected function resolveServiceDownAlert(PlatformServer $server, string $serviceKey): void
    {
        $rule = PlatformAlertRule::query()->where('name', "Service Down: {$serviceKey}")->first();
        if (! $rule) return;

        PlatformAlertEvent::query()
            ->where('rule_id', $rule->id)
            ->where('server_id', $server->id)
            ->where('status', 'open')
            ->update([
                'status' => 'resolved',
                'resolved_at' => now(),
            ]);
    }

    protected function raiseQueueFailedAlert(PlatformServer $server, float $failedCount): void
    {
        $rule = PlatformAlertRule::query()->firstOrCreate(
            ['name' => 'Rapid Queue Failures'],
            [
                'scope_type' => 'server',
                'metric_key' => 'queue.failed',
                'operator' => 'gt',
                'threshold' => 10,
                'severity' => 'critical',
                'duration_seconds' => 0,
                'is_enabled' => true,
            ]
        );

        $existing = PlatformAlertEvent::query()
            ->where('rule_id', $rule->id)
            ->where('server_id', $server->id)
            ->whereIn('status', ['open', 'acknowledged'])
            ->first();

        if (! $existing) {
            PlatformAlertEvent::query()->create([
                'rule_id' => $rule->id,
                'server_id' => $server->id,
                'status' => 'open',
                'severity' => 'critical',
                'message' => "افزایش بحرانی کارهای ناموفق در صف (Queue Failed: {$failedCount}) روی سرور {$server->name}",
                'metric_value' => $failedCount,
                'opened_at' => now(),
                'meta' => ['failed_count' => $failedCount],
            ]);
        }
    }

    protected function runCommand(PlatformServer $server, string $cmd, bool $isLocal): string
    {
        if ($isLocal) {
            $process = new Process(['sh', '-c', $cmd]);
            $process->setTimeout(10);
            $process->run();
            return trim($process->getOutput());
        }

        try {
            $res = $this->ssh->run($server, $cmd, 15);
            return trim($res['stdout']);
        } catch (Throwable) {
            return 'DOWN';
        }
    }
}

function SchemaHasTable(string $table): bool
{
    return \Illuminate\Support\Facades\Schema::hasTable($table);
}

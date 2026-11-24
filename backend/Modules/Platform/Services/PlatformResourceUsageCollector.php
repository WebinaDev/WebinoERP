<?php

namespace Modules\Platform\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Modules\Platform\Entities\PlatformAlertEvent;
use Modules\Platform\Entities\PlatformAlertRule;
use Modules\Platform\Entities\PlatformEnvironment;
use Modules\Platform\Entities\PlatformMetricSnapshot;
use Modules\Platform\Entities\PlatformProject;
use Modules\Platform\Entities\PlatformProjectQuota;
use Modules\Platform\Entities\PlatformResource;
use Modules\Platform\Entities\PlatformResourceQuota;
use Modules\Platform\Entities\PlatformServer;
use Symfony\Component\Process\Process;
use Throwable;

class PlatformResourceUsageCollector
{
    public function __construct(
        protected SshExecutor $ssh
    ) {}

    /**
     * Collect usage for all active resources and aggregate by project.
     */
    public function collectAll(): array
    {
        $resources = PlatformResource::query()
            ->with(['server', 'environment'])
            ->get();

        $resourceResults = [];
        $affectedProjects = [];

        foreach ($resources as $resource) {
            try {
                $res = $this->collectResource($resource);
                $resourceResults[$resource->id] = $res;
                if ($resource->environment?->project_id) {
                    $affectedProjects[$resource->environment->project_id] = true;
                }
            } catch (Throwable $e) {
                Log::warning("Failed to collect usage for resource {$resource->id}: " . $e->getMessage());
            }
        }

        // Aggregate projects
        $projectResults = [];
        foreach (array_keys($affectedProjects) as $projectId) {
            $project = PlatformProject::query()->find($projectId);
            if ($project) {
                try {
                    $projectResults[$projectId] = $this->aggregateProject($project);
                } catch (Throwable $e) {
                    Log::warning("Failed to aggregate usage for project {$projectId}: " . $e->getMessage());
                }
            }
        }

        return [
            'resources_collected' => count($resourceResults),
            'projects_aggregated' => count($projectResults),
            'resources' => $resourceResults,
            'projects' => $projectResults,
        ];
    }

    /**
     * Collect disk, inode, and container stats (CPU, RAM) for a resource.
     */
    public function collectResource(PlatformResource $resource): array
    {
        $server = $resource->server ?? PlatformServer::query()->find($resource->server_id);
        $now = Carbon::now();
        $projectId = $resource->environment?->project_id;

        $stats = $this->queryResourceRawStats($resource, $server);

        // Store snapshots with scope=resource
        $metrics = [
            'disk.used' => ['value' => $stats['disk_used'], 'unit' => 'bytes'],
            'inode.used' => ['value' => $stats['inode_used'], 'unit' => 'count'],
            'cpu.usage' => ['value' => $stats['cpu_usage'], 'unit' => 'percent'],
            'mem.used' => ['value' => $stats['mem_used'], 'unit' => 'bytes'],
        ];

        foreach ($metrics as $metricKey => $m) {
            PlatformMetricSnapshot::query()->create([
                'server_id' => $server?->id,
                'project_id' => $projectId,
                'resource_id' => $resource->id,
                'scope' => 'resource',
                'metric_key' => $metricKey,
                'value' => (float) $m['value'],
                'unit' => $m['unit'],
                'labels' => [
                    'source' => $stats['source'],
                    'resource_name' => $resource->name,
                ],
                'collected_at' => $now,
            ]);
        }

        // Evaluate resource quota
        $this->evaluateResourceQuota($resource, $stats, $server?->id, $projectId);

        return [
            'resource_id' => $resource->id,
            'stats' => $stats,
            'collected_at' => $now->toIso8601String(),
        ];
    }

    /**
     * Aggregate resource snapshots into project snapshots.
     */
    public function aggregateProject(PlatformProject $project): array
    {
        $now = Carbon::now();
        $resourceIds = PlatformResource::query()
            ->whereHas('environment', fn ($q) => $q->where('project_id', $project->id))
            ->pluck('id');

        if ($resourceIds->isEmpty()) {
            return ['project_id' => $project->id, 'aggregated' => 0];
        }

        // Fetch latest resource snapshots within the last 15 minutes
        $since = Carbon::now()->subMinutes(15);
        $snapshots = PlatformMetricSnapshot::query()
            ->whereIn('resource_id', $resourceIds)
            ->where('scope', 'resource')
            ->where('collected_at', '>=', $since)
            ->orderBy('collected_at', 'desc')
            ->get()
            ->groupBy('resource_id');

        $totalDiskBytes = 0.0;
        $totalMemBytes = 0.0;
        $totalCpuPercent = 0.0;
        $totalInodes = 0;

        foreach ($snapshots as $rId => $group) {
            $byKey = $group->groupBy('metric_key');
            $totalDiskBytes += (float) ($byKey->get('disk.used')?->first()->value ?? 0);
            $totalMemBytes += (float) ($byKey->get('mem.used')?->first()->value ?? 0);
            $totalCpuPercent += (float) ($byKey->get('cpu.usage')?->first()->value ?? 0);
            $totalInodes += (int) ($byKey->get('inode.used')?->first()->value ?? 0);
        }

        // Save project snapshots with scope=project
        $projectMetrics = [
            'project.disk.used' => ['value' => $totalDiskBytes, 'unit' => 'bytes'],
            'project.ram.used' => ['value' => $totalMemBytes, 'unit' => 'bytes'],
            'project.cpu.usage' => ['value' => $totalCpuPercent, 'unit' => 'percent'],
            'project.inode.used' => ['value' => $totalInodes, 'unit' => 'count'],
        ];

        foreach ($projectMetrics as $k => $m) {
            PlatformMetricSnapshot::query()->create([
                'server_id' => null,
                'project_id' => $project->id,
                'resource_id' => null,
                'scope' => 'project',
                'metric_key' => $k,
                'value' => (float) $m['value'],
                'unit' => $m['unit'],
                'labels' => ['project_name' => $project->name],
                'collected_at' => $now,
            ]);
        }

        // Evaluate project quota
        $this->evaluateProjectQuota($project, [
            'disk_used_mb' => $totalDiskBytes / (1024 * 1024),
            'ram_used_mb' => $totalMemBytes / (1024 * 1024),
            'cpu_usage' => $totalCpuPercent,
            'inode_used' => $totalInodes,
        ]);

        return [
            'project_id' => $project->id,
            'disk_bytes' => $totalDiskBytes,
            'ram_bytes' => $totalMemBytes,
            'cpu_usage' => $totalCpuPercent,
            'inode_used' => $totalInodes,
        ];
    }

    /**
     * Query disk, inode, and docker/cgroup stats from host or remote server.
     */
    protected function queryResourceRawStats(PlatformResource $resource, ?PlatformServer $server): array
    {
        $name = $resource->name;
        // Paths: /var/lib/webino/sites/{name}, volumes, logs
        $escapedName = escapeshellarg($name);

        $sh = <<<SH
# 1. Disk and inode calculation
PATH1="/var/lib/webino/sites/$name"
PATH2="/var/lib/docker/volumes/{$name}_data/_data"
TARGET_PATH=""
if [ -d "\$PATH1" ]; then
    TARGET_PATH="\$PATH1"
elif [ -d "\$PATH2" ]; then
    TARGET_PATH="\$PATH2"
fi

DISK_BYTES=0
INODE_COUNT=0
if [ -n "\$TARGET_PATH" ]; then
    DISK_BYTES=$(du -sb "\$TARGET_PATH" 2>/dev/null | awk '{print $1}')
    INODE_COUNT=$(find "\$TARGET_PATH" 2>/dev/null | wc -l)
fi
[ -z "\$DISK_BYTES" ] && DISK_BYTES=0
[ -z "\$INODE_COUNT" ] && INODE_COUNT=0

# 2. Docker container stats (CPU & Memory)
CPU_PCT=0
MEM_BYTES=0
SOURCE="estimated"

CID=$(docker ps -qf "name=$name" 2>/dev/null | head -1)
if [ -n "\$CID" ]; then
    # docker stats: format {{.CPUPerc}},{{.MemUsage}}
    STATS_LINE=$(docker stats --no-stream --format "{{.CPUPerc}},{{.MemUsage}}" "\$CID" 2>/dev/null | head -1)
    if [ -n "\$STATS_LINE" ]; then
        RAW_CPU=$(echo "\$STATS_LINE" | cut -d',' -f1 | tr -d '% ')
        RAW_MEM=$(echo "\$STATS_LINE" | cut -d',' -f2 | awk '{print $1}')
        RAW_UNIT=$(echo "\$STATS_LINE" | cut -d',' -f2 | awk '{print $2}')
        [ -n "\$RAW_CPU" ] && CPU_PCT="\$RAW_CPU"
        SOURCE="docker"

        # Convert mem to bytes
        case "\$RAW_UNIT" in
            GiB|GB) MEM_BYTES=$(awk "BEGIN {print int(\$RAW_MEM * 1024 * 1024 * 1024)}") ;;
            MiB|MB) MEM_BYTES=$(awk "BEGIN {print int(\$RAW_MEM * 1024 * 1024)}") ;;
            KiB|kB) MEM_BYTES=$(awk "BEGIN {print int(\$RAW_MEM * 1024)}") ;;
            B) MEM_BYTES=\$RAW_MEM ;;
        esac
    fi
fi
[ -z "\$CPU_PCT" ] && CPU_PCT=0
[ -z "\$MEM_BYTES" ] && MEM_BYTES=0

echo "DISK_BYTES=\$DISK_BYTES"
echo "INODE_COUNT=\$INODE_COUNT"
echo "CPU_PCT=\$CPU_PCT"
echo "MEM_BYTES=\$MEM_BYTES"
echo "SOURCE=\$SOURCE"
SH;

        $stdout = '';
        if ($server && ($server->is_localhost || in_array($server->ip, ['127.0.0.1', 'localhost', '::1'], true))) {
            $process = new Process(['sh', '-c', $sh]);
            $process->setTimeout(30);
            $process->run();
            $stdout = $process->getOutput();
        } elseif ($server) {
            $res = $this->ssh->run($server, $sh, 30);
            $stdout = $res['stdout'];
        }

        $vars = [];
        foreach (explode("\n", trim($stdout)) as $line) {
            if (str_contains($line, '=')) {
                [$k, $v] = explode('=', trim($line), 2);
                $vars[$k] = $v;
            }
        }

        return [
            'disk_used' => (float) ($vars['DISK_BYTES'] ?? 0),
            'inode_used' => (int) ($vars['INODE_COUNT'] ?? 0),
            'cpu_usage' => (float) ($vars['CPU_PCT'] ?? 0),
            'mem_used' => (float) ($vars['MEM_BYTES'] ?? 0),
            'source' => $vars['SOURCE'] ?? 'estimated',
        ];
    }

    /**
     * Evaluate resource quota rules (Soft -> warning alert, Hard -> critical alert).
     */
    protected function evaluateResourceQuota(PlatformResource $resource, array $stats, ?int $serverId, ?int $projectId): void
    {
        $quota = PlatformResourceQuota::query()
            ->where('resource_id', $resource->id)
            ->where('is_enabled', true)
            ->first();

        if (! $quota) {
            return;
        }

        $diskMb = $stats['disk_used'] / (1024 * 1024);
        $ramMb = $stats['mem_used'] / (1024 * 1024);
        $cpu = (float) $stats['cpu_usage'];
        $inode = (int) $stats['inode_used'];

        // Evaluate Disk Quota
        $this->checkThreshold(
            'resource',
            $resource->id,
            $serverId,
            $projectId,
            'disk_mb',
            $diskMb,
            $quota->disk_mb_soft,
            $quota->disk_mb_hard,
            "مصرف دیسک منبع {$resource->name}",
            'MB',
            $quota->enforce_action
        );

        // Evaluate RAM Quota
        $this->checkThreshold(
            'resource',
            $resource->id,
            $serverId,
            $projectId,
            'ram_mb',
            $ramMb,
            $quota->ram_mb_soft,
            $quota->ram_mb_hard,
            "مصرف حافظه منبع {$resource->name}",
            'MB',
            $quota->enforce_action
        );

        // Evaluate CPU Quota
        $this->checkThreshold(
            'resource',
            $resource->id,
            $serverId,
            $projectId,
            'cpu_percent',
            $cpu,
            $quota->cpu_percent_soft,
            $quota->cpu_percent_hard,
            "مصرف پردازنده منبع {$resource->name}",
            '%',
            $quota->enforce_action
        );

        // Evaluate Inode Quota
        $this->checkThreshold(
            'resource',
            $resource->id,
            $serverId,
            $projectId,
            'inode',
            $inode,
            $quota->inode_soft,
            $quota->inode_hard,
            "تعداد Inode منبع {$resource->name}",
            'count',
            $quota->enforce_action
        );
    }

    /**
     * Evaluate project quota rules.
     */
    protected function evaluateProjectQuota(PlatformProject $project, array $usage): void
    {
        $quota = PlatformProjectQuota::query()
            ->where('project_id', $project->id)
            ->where('is_enabled', true)
            ->first();

        if (! $quota) {
            return;
        }

        $this->checkThreshold(
            'project',
            null,
            null,
            $project->id,
            'disk_mb',
            $usage['disk_used_mb'],
            $quota->disk_mb_soft,
            $quota->disk_mb_hard,
            "مصرف تجمیعی دیسک پروژه {$project->name}",
            'MB',
            $quota->enforce_action
        );

        $this->checkThreshold(
            'project',
            null,
            null,
            $project->id,
            'ram_mb',
            $usage['ram_used_mb'],
            $quota->ram_mb_soft,
            $quota->ram_mb_hard,
            "مصرف تجمیعی حافظه پروژه {$project->name}",
            'MB',
            $quota->enforce_action
        );
    }

    protected function checkThreshold(
        string $scope,
        ?int $resourceId,
        ?int $serverId,
        ?int $projectId,
        string $metricName,
        float $actualValue,
        ?float $soft,
        ?float $hard,
        string $subjectLabel,
        string $unit,
        string $enforceAction = 'alert'
    ): void {
        // Hard threshold check (Critical)
        if ($hard !== null && $hard > 0 && $actualValue >= $hard) {
            $this->raiseQuotaAlert(
                $scope,
                $resourceId,
                $serverId,
                $projectId,
                $metricName,
                'critical',
                "{$subjectLabel} از سقف مجاز سخت (Hard Quota: {$hard} {$unit}) فراتر رفت: {$actualValue} {$unit}",
                $actualValue,
                $hard,
                'hard',
                $enforceAction
            );
            return;
        }

        // Soft threshold check (Warning)
        if ($soft !== null && $soft > 0 && $actualValue >= $soft) {
            $this->raiseQuotaAlert(
                $scope,
                $resourceId,
                $serverId,
                $projectId,
                $metricName,
                'warning',
                "{$subjectLabel} از سقف هشدار نرم (Soft Quota: {$soft} {$unit}) عبور کرد: {$actualValue} {$unit}",
                $actualValue,
                $soft,
                'soft',
                $enforceAction
            );
            return;
        }

        // Auto-resolve if under soft threshold
        PlatformAlertEvent::query()
            ->where('resource_id', $resourceId)
            ->where('project_id', $projectId)
            ->where('status', 'open')
            ->where('meta->metric_name', $metricName)
            ->update([
                'status' => 'resolved',
                'resolved_at' => now(),
            ]);
    }

    protected function raiseQuotaAlert(
        string $scope,
        ?int $resourceId,
        ?int $serverId,
        ?int $projectId,
        string $metricName,
        string $severity,
        string $message,
        float $actualValue,
        float $threshold,
        string $quotaLevel,
        string $enforceAction
    ): void {
        // Ensure a dummy or synthetic rule exists or find matching
        $rule = PlatformAlertRule::query()->firstOrCreate(
            ['name' => "Quota: {$metricName}"],
            [
                'scope_type' => $scope === 'resource' ? 'resource' : 'project',
                'metric_key' => "quota.{$metricName}",
                'operator' => 'gte',
                'threshold' => $threshold,
                'severity' => $severity,
                'duration_seconds' => 0,
                'is_enabled' => true,
            ]
        );

        $existing = PlatformAlertEvent::query()
            ->where('rule_id', $rule->id)
            ->where('resource_id', $resourceId)
            ->where('project_id', $projectId)
            ->whereIn('status', ['open', 'acknowledged'])
            ->first();

        if (! $existing) {
            PlatformAlertEvent::query()->create([
                'rule_id' => $rule->id,
                'server_id' => $serverId,
                'project_id' => $projectId,
                'resource_id' => $resourceId,
                'status' => 'open',
                'severity' => $severity,
                'message' => $message,
                'metric_value' => $actualValue,
                'opened_at' => now(),
                'meta' => [
                    'scope' => $scope,
                    'metric_name' => $metricName,
                    'quota_level' => $quotaLevel,
                    'threshold' => $threshold,
                    'enforce_action' => $enforceAction,
                    'suspend_ready' => $enforceAction === 'suspend' && $quotaLevel === 'hard',
                ],
            ]);
        } else {
            $existing->update([
                'metric_value' => $actualValue,
                'severity' => $severity,
                'message' => $message,
            ]);
        }
    }
}

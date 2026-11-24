<?php

namespace Modules\Platform\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Platform\Entities\PlatformAlertEvent;
use Modules\Platform\Entities\PlatformMetricRollupDaily;
use Modules\Platform\Entities\PlatformMetricRollupHourly;
use Modules\Platform\Entities\PlatformMetricSnapshot;
use Modules\Platform\Entities\PlatformProject;
use Modules\Platform\Entities\PlatformProjectQuota;
use Modules\Platform\Entities\PlatformResource;
use Modules\Platform\Entities\PlatformResourceQuota;
use Modules\Platform\Entities\PlatformServer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MonitoringReportsController extends Controller
{
    /**
     * GET /api/v1/platform/monitoring/reports/usage
     * Supports: from, to, server_id, project_id, resource_id, group_by=hour|day, export=csv
     */
    public function usage(Request $request)
    {
        $from = $request->filled('from') ? Carbon::parse($request->query('from')) : Carbon::now()->subDays(7);
        $to = $request->filled('to') ? Carbon::parse($request->query('to')) : Carbon::now();
        $groupBy = $request->query('group_by', 'day');
        $serverId = $request->query('server_id');
        $projectId = $request->query('project_id');
        $resourceId = $request->query('resource_id');

        $query = PlatformMetricSnapshot::query()
            ->whereBetween('collected_at', [$from, $to]);

        if ($serverId) $query->where('server_id', $serverId);
        if ($projectId) $query->where('project_id', $projectId);
        if ($resourceId) $query->where('resource_id', $resourceId);

        $records = $query->select([
            'metric_key',
            'scope',
            DB::raw("DATE_TRUNC('" . ($groupBy === 'hour' ? 'hour' : 'day') . "', collected_at) as bucket"),
            DB::raw('AVG(value) as avg_value'),
            DB::raw('MAX(value) as max_value'),
            DB::raw('MIN(value) as min_value'),
            DB::raw('COUNT(*) as sample_count'),
        ])
            ->groupBy('metric_key', 'scope', 'bucket')
            ->orderBy('bucket', 'asc')
            ->get();

        // CSV Export handling
        if ($request->query('export') === 'csv') {
            return new StreamedResponse(function () use ($records) {
                $out = fopen('php://output', 'w');
                fputcsv($out, ['Bucket', 'Metric Key', 'Scope', 'Avg Value', 'Max Value', 'Min Value', 'Sample Count']);
                foreach ($records as $r) {
                    fputcsv($out, [
                        $r->bucket,
                        $r->metric_key,
                        $r->scope,
                        round((float) $r->avg_value, 4),
                        round((float) $r->max_value, 4),
                        round((float) $r->min_value, 4),
                        $r->sample_count,
                    ]);
                }
                fclose($out);
            }, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="usage-report-' . now()->format('YmdHis') . '.csv"',
            ]);
        }

        return response()->json([
            'data' => [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
                'group_by' => $groupBy,
                'series' => $records,
            ],
        ]);
    }

    /**
     * GET /api/v1/platform/monitoring/reports/top-consumers
     * Query: metric=cpu|mem|disk (default cpu), limit=10, server_id, project_id
     */
    public function topConsumers(Request $request): JsonResponse
    {
        $metricType = $request->query('metric', 'cpu'); // cpu|mem|disk
        $limit = min(50, max(1, $request->integer('limit', 10)));
        $serverId = $request->query('server_id');
        $projectId = $request->query('project_id');

        $metricKey = match ($metricType) {
            'mem', 'memory' => 'mem.used',
            'disk' => 'disk.used',
            default => 'cpu.usage',
        };

        // Query resources latest metric value
        $query = PlatformMetricSnapshot::query()
            ->where('scope', 'resource')
            ->where('metric_key', $metricKey)
            ->whereNotNull('resource_id');

        if ($serverId) $query->where('server_id', $serverId);
        if ($projectId) $query->where('project_id', $projectId);

        // Fetch the most recent snapshot per resource
        $latestPerResource = $query->orderBy('collected_at', 'desc')
            ->get()
            ->groupBy('resource_id')
            ->map(fn ($g) => $g->first());

        // Sort descending
        $sorted = $latestPerResource->sortByDesc(fn ($snap) => (float) $snap->value)->take($limit);

        $resourceIds = $sorted->pluck('resource_id')->filter()->all();
        $resources = PlatformResource::query()
            ->with(['server', 'environment.project'])
            ->whereIn('id', $resourceIds)
            ->get()
            ->keyBy('id');

        $items = [];
        foreach ($sorted as $snap) {
            $res = $resources->get($snap->resource_id);
            $items[] = [
                'resource_id' => $snap->resource_id,
                'resource_name' => $res?->name ?? "Resource #{$snap->resource_id}",
                'resource_type' => $res?->type ?? 'application',
                'server_name' => $res?->server?->name ?? '—',
                'project_name' => $res?->environment?->project?->name ?? '—',
                'metric_key' => $metricKey,
                'value' => (float) $snap->value,
                'unit' => $snap->unit,
                'collected_at' => $snap->collected_at?->toIso8601String(),
            ];
        }

        return response()->json([
            'data' => [
                'metric' => $metricType,
                'limit' => $limit,
                'consumers' => array_values($items),
            ],
        ]);
    }

    /**
     * GET /api/v1/platform/monitoring/reports/quota-risk
     * Identifies resources and projects approaching or exceeding their soft/hard quotas.
     */
    public function quotaRisk(): JsonResponse
    {
        $risks = [];

        // 1. Evaluate Resource Quotas
        $resourceQuotas = PlatformResourceQuota::query()
            ->where('is_enabled', true)
            ->with(['resource.server', 'resource.environment.project'])
            ->get();

        foreach ($resourceQuotas as $rq) {
            $resource = $rq->resource;
            if (! $resource) continue;

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

            // Risk calculation
            $checkItem = function (string $metric, float $val, ?float $soft, ?float $hard, string $unit) use (&$risks, $resource, $rq) {
                if ($hard && $val >= $hard) {
                    $risks[] = [
                        'type' => 'resource',
                        'id' => $resource->id,
                        'name' => $resource->name,
                        'server_name' => $resource->server?->name ?? '—',
                        'project_name' => $resource->environment?->project?->name ?? '—',
                        'metric' => $metric,
                        'current_value' => round($val, 2),
                        'threshold' => $hard,
                        'unit' => $unit,
                        'risk_level' => 'hard_breach',
                        'severity' => 'critical',
                        'action' => $rq->enforce_action,
                        'usage_percent' => $hard > 0 ? round(($val / $hard) * 100, 1) : 100,
                    ];
                } elseif ($soft && $val >= $soft) {
                    $risks[] = [
                        'type' => 'resource',
                        'id' => $resource->id,
                        'name' => $resource->name,
                        'server_name' => $resource->server?->name ?? '—',
                        'project_name' => $resource->environment?->project?->name ?? '—',
                        'metric' => $metric,
                        'current_value' => round($val, 2),
                        'threshold' => $soft,
                        'unit' => $unit,
                        'risk_level' => 'soft_warning',
                        'severity' => 'warning',
                        'action' => $rq->enforce_action,
                        'usage_percent' => $soft > 0 ? round(($val / $soft) * 100, 1) : 100,
                    ];
                }
            };

            $checkItem('disk', $diskMb, $rq->disk_mb_soft, $rq->disk_mb_hard, 'MB');
            $checkItem('ram', $ramMb, $rq->ram_mb_soft, $rq->ram_mb_hard, 'MB');
            $checkItem('cpu', $cpu, $rq->cpu_percent_soft, $rq->cpu_percent_hard, '%');
        }

        // 2. Evaluate Project Quotas
        $projectQuotas = PlatformProjectQuota::query()
            ->where('is_enabled', true)
            ->with('project')
            ->get();

        foreach ($projectQuotas as $pq) {
            $project = $pq->project;
            if (! $project) continue;

            $latest = PlatformMetricSnapshot::query()
                ->where('project_id', $project->id)
                ->where('scope', 'project')
                ->orderBy('collected_at', 'desc')
                ->get()
                ->groupBy('metric_key')
                ->map(fn ($g) => (float) $g->first()->value);

            $diskMb = $latest->get('project.disk.used', 0.0) / (1024 * 1024);
            $ramMb = $latest->get('project.ram.used', 0.0) / (1024 * 1024);

            if ($pq->disk_mb_hard && $diskMb >= $pq->disk_mb_hard) {
                $risks[] = [
                    'type' => 'project',
                    'id' => $project->id,
                    'name' => $project->name,
                    'server_name' => 'Cluster',
                    'project_name' => $project->name,
                    'metric' => 'disk',
                    'current_value' => round($diskMb, 2),
                    'threshold' => $pq->disk_mb_hard,
                    'unit' => 'MB',
                    'risk_level' => 'hard_breach',
                    'severity' => 'critical',
                    'action' => $pq->enforce_action,
                    'usage_percent' => round(($diskMb / $pq->disk_mb_hard) * 100, 1),
                ];
            }
        }

        return response()->json([
            'data' => [
                'total_risks' => count($risks),
                'risks' => array_values($risks),
            ],
        ]);
    }

    /**
     * GET /api/v1/platform/monitoring/reports/alerts-summary
     * Filters: from, to, server_id, project_id, severity
     */
    public function alertsSummary(Request $request): JsonResponse
    {
        $from = $request->filled('from') ? Carbon::parse($request->query('from')) : Carbon::now()->subDays(30);
        $to = $request->filled('to') ? Carbon::parse($request->query('to')) : Carbon::now();

        $query = PlatformAlertEvent::query()->whereBetween('opened_at', [$from, $to]);

        if ($request->filled('server_id')) $query->where('server_id', $request->query('server_id'));
        if ($request->filled('project_id')) $query->where('project_id', $request->query('project_id'));
        if ($request->filled('severity')) $query->where('severity', $request->query('severity'));

        $totalAlerts = (clone $query)->count();
        $byStatus = (clone $query)->select('status', DB::raw('COUNT(*) as count'))->groupBy('status')->pluck('count', 'status');
        $bySeverity = (clone $query)->select('severity', DB::raw('COUNT(*) as count'))->groupBy('severity')->pluck('count', 'severity');

        // Recent alerts
        $recent = (clone $query)->with(['rule', 'server', 'project'])
            ->orderBy('id', 'desc')
            ->limit(20)
            ->get()
            ->map(fn ($al) => [
                'id' => $al->id,
                'rule_name' => $al->rule?->name,
                'server_name' => $al->server?->name,
                'project_name' => $al->project?->name,
                'status' => $al->status,
                'severity' => $al->severity,
                'message' => $al->message,
                'opened_at' => $al->opened_at?->toIso8601String(),
            ]);

        return response()->json([
            'data' => [
                'total_alerts' => $totalAlerts,
                'by_status' => $byStatus,
                'by_severity' => $bySeverity,
                'recent_alerts' => $recent,
            ],
        ]);
    }
}

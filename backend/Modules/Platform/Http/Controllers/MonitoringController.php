<?php

namespace Modules\Platform\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Platform\Entities\PlatformAlertEvent;
use Modules\Platform\Entities\PlatformAlertRule;
use Modules\Platform\Entities\PlatformMetricRollupHourly;
use Modules\Platform\Entities\PlatformMetricSnapshot;
use Modules\Platform\Entities\PlatformServer;
use Modules\Platform\Services\PlatformServerMetricsCollector;

class MonitoringController extends Controller
{
    /**
     * GET /api/v1/platform/monitoring/overview
     * Returns:
     * - healthy/warning/critical server counts
     * - latest CPU/RAM/Disk for each server
     * - count of open alert events
     */
    public function overview(): JsonResponse
    {
        $servers = PlatformServer::query()->get();
        $openAlertsCount = PlatformAlertEvent::query()->where('status', 'open')->count();

        $serverStats = [];
        $healthyCount = 0;
        $warningCount = 0;
        $criticalCount = 0;

        foreach ($servers as $server) {
            // Get latest snapshots for cpu.usage, mem.usage_percent, disk.usage_percent
            $latestMetrics = PlatformMetricSnapshot::query()
                ->where('server_id', $server->id)
                ->whereIn('metric_key', ['cpu.usage', 'mem.usage_percent', 'disk.usage_percent'])
                ->orderBy('collected_at', 'desc')
                ->get()
                ->groupBy('metric_key')
                ->map(fn ($group) => (float) $group->first()->value);

            $cpu = $latestMetrics->get('cpu.usage', 0.0);
            $ram = $latestMetrics->get('mem.usage_percent', 0.0);
            $disk = $latestMetrics->get('disk.usage_percent', 0.0);

            // Server-specific open alert severity
            $hasCriticalAlert = PlatformAlertEvent::query()
                ->where('server_id', $server->id)
                ->where('status', 'open')
                ->where('severity', 'critical')
                ->exists();

            $hasWarningAlert = PlatformAlertEvent::query()
                ->where('server_id', $server->id)
                ->where('status', 'open')
                ->where('severity', 'warning')
                ->exists();

            if ($server->status === 'unreachable' || $hasCriticalAlert || $cpu >= 95 || $ram >= 95 || $disk >= 95) {
                $statusLevel = 'critical';
                $criticalCount++;
            } elseif ($hasWarningAlert || $cpu >= 80 || $ram >= 80 || $disk >= 85) {
                $statusLevel = 'warning';
                $warningCount++;
            } else {
                $statusLevel = 'healthy';
                $healthyCount++;
            }

            $serverStats[] = [
                'id' => $server->id,
                'name' => $server->name,
                'ip' => $server->ip,
                'status' => $server->status,
                'health_status' => $statusLevel,
                'last_seen_at' => $server->last_seen_at?->toIso8601String(),
                'latest_metrics' => [
                    'cpu_usage' => $cpu,
                    'mem_usage_percent' => $ram,
                    'disk_usage_percent' => $disk,
                ],
            ];
        }

        return response()->json([
            'data' => [
                'counts' => [
                    'total_servers' => $servers->count(),
                    'healthy_servers' => $healthyCount,
                    'warning_servers' => $warningCount,
                    'critical_servers' => $criticalCount,
                    'open_alerts' => $openAlertsCount,
                ],
                'servers' => $serverStats,
            ],
        ]);
    }

    /**
     * GET /api/v1/platform/servers/{id}/metrics
     * Query params:
     * - from, to
     * - metric_keys[]
     * - interval=raw|hourly
     */
    public function serverMetrics(Request $request, int $id): JsonResponse
    {
        $server = PlatformServer::query()->findOrFail($id);

        $from = $request->filled('from') ? Carbon::parse($request->query('from')) : Carbon::now()->subHours(24);
        $to = $request->filled('to') ? Carbon::parse($request->query('to')) : Carbon::now();
        $interval = $request->query('interval', 'raw');
        $metricKeys = (array) $request->query('metric_keys', []);

        if ($interval === 'hourly') {
            $query = PlatformMetricRollupHourly::query()
                ->where('server_id', $server->id)
                ->whereBetween('bucket_start', [$from, $to])
                ->orderBy('bucket_start', 'asc');

            if (! empty($metricKeys)) {
                $query->whereIn('metric_key', $metricKeys);
            }

            $rows = $query->get()->map(fn ($r) => [
                'metric_key' => $r->metric_key,
                'bucket_start' => $r->bucket_start?->toIso8601String(),
                'value_avg' => (float) $r->value_avg,
                'value_max' => (float) $r->value_max,
                'value_min' => (float) $r->value_min,
                'sample_count' => (int) $r->sample_count,
                'unit' => $r->unit,
            ]);
        } else {
            $query = PlatformMetricSnapshot::query()
                ->where('server_id', $server->id)
                ->whereBetween('collected_at', [$from, $to])
                ->orderBy('collected_at', 'asc');

            if (! empty($metricKeys)) {
                $query->whereIn('metric_key', $metricKeys);
            }

            $rows = $query->limit(2000)->get()->map(fn ($s) => [
                'metric_key' => $s->metric_key,
                'value' => (float) $s->value,
                'unit' => $s->unit,
                'labels' => $s->labels,
                'collected_at' => $s->collected_at?->toIso8601String(),
            ]);
        }

        return response()->json([
            'data' => [
                'server_id' => $server->id,
                'interval' => $interval,
                'metrics' => $rows,
            ],
        ]);
    }

    /**
     * POST /api/v1/platform/servers/{id}/metrics/collect
     * Manual real-time collection.
     */
    public function collectServerMetrics(PlatformServerMetricsCollector $collector, int $id): JsonResponse
    {
        $server = PlatformServer::query()->findOrFail($id);
        $result = $collector->collect($server);

        return response()->json([
            'data' => $result,
            'message' => 'متریک‌های سرور با موفقیت دریافت و ذخیره شدند.',
        ]);
    }

    /**
     * GET /api/v1/platform/monitoring/alerts
     * Filter by status, severity, server_id
     */
    public function alerts(Request $request): JsonResponse
    {
        $query = PlatformAlertEvent::query()
            ->with(['rule', 'server'])
            ->orderBy('id', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('severity')) {
            $query->where('severity', $request->query('severity'));
        }
        if ($request->filled('server_id')) {
            $query->where('server_id', $request->query('server_id'));
        }

        $items = $query->paginate($request->integer('per_page', 30))->through(fn ($event) => [
            'id' => $event->id,
            'rule_id' => $event->rule_id,
            'rule_name' => $event->rule?->name,
            'server_id' => $event->server_id,
            'server_name' => $event->server?->name,
            'status' => $event->status,
            'severity' => $event->severity,
            'message' => $event->message,
            'metric_value' => (float) $event->metric_value,
            'opened_at' => $event->opened_at?->toIso8601String(),
            'acknowledged_at' => $event->acknowledged_at?->toIso8601String(),
            'resolved_at' => $event->resolved_at?->toIso8601String(),
            'meta' => $event->meta,
        ]);

        return response()->json($items);
    }

    /**
     * POST /api/v1/platform/monitoring/alerts/{id}/ack
     */
    public function ackAlert(int $id): JsonResponse
    {
        $event = PlatformAlertEvent::query()->findOrFail($id);
        $event->update([
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
        ]);

        return response()->json([
            'data' => $event,
            'message' => 'هشدار با موفقیت تأیید شد.',
        ]);
    }

    /**
     * POST /api/v1/platform/monitoring/alerts/{id}/resolve
     */
    public function resolveAlert(int $id): JsonResponse
    {
        $event = PlatformAlertEvent::query()->findOrFail($id);
        $event->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        return response()->json([
            'data' => $event,
            'message' => 'هشدار به عنوان برطرف‌شده ثبت گردید.',
        ]);
    }

    /**
     * GET /api/v1/platform/monitoring/rules
     */
    public function rules(): JsonResponse
    {
        $rules = PlatformAlertRule::query()->orderBy('id', 'asc')->get();

        return response()->json(['data' => $rules]);
    }

    /**
     * POST /api/v1/platform/monitoring/rules
     */
    public function storeRule(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'scope_type' => 'required|in:server,project,resource,global',
            'scope_id' => 'nullable|integer',
            'metric_key' => 'required|string|max:64',
            'operator' => 'required|in:gt,gte,lt,lte',
            'threshold' => 'required|numeric',
            'duration_seconds' => 'nullable|integer|min:10',
            'severity' => 'required|in:info,warning,critical',
            'channel_ids' => 'nullable|array',
            'is_enabled' => 'nullable|boolean',
        ]);

        $data['duration_seconds'] = $data['duration_seconds'] ?? 300;
        $data['is_enabled'] = $data['is_enabled'] ?? true;

        $rule = PlatformAlertRule::query()->create($data);

        return response()->json(['data' => $rule, 'message' => 'قانون هشدار ایجاد شد.'], 201);
    }

    /**
     * PUT /api/v1/platform/monitoring/rules/{id}
     */
    public function updateRule(Request $request, int $id): JsonResponse
    {
        $rule = PlatformAlertRule::query()->findOrFail($id);

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:120',
            'scope_type' => 'sometimes|required|in:server,project,resource,global',
            'scope_id' => 'nullable|integer',
            'metric_key' => 'sometimes|required|string|max:64',
            'operator' => 'sometimes|required|in:gt,gte,lt,lte',
            'threshold' => 'sometimes|required|numeric',
            'duration_seconds' => 'sometimes|nullable|integer|min:10',
            'severity' => 'sometimes|required|in:info,warning,critical',
            'channel_ids' => 'nullable|array',
            'is_enabled' => 'sometimes|boolean',
        ]);

        $rule->update($data);

        return response()->json(['data' => $rule, 'message' => 'قانون هشدار بروزرسانی شد.']);
    }
    /**
     * GET /api/v1/platform/servers/{id}/services/health
     */
    public function serviceHealth(Request $request, int $id): JsonResponse
    {
        $server = PlatformServer::query()->findOrFail($id);

        $services = \Modules\Platform\Entities\PlatformServiceHealth::query()
            ->where('server_id', $server->id)
            ->orderBy('id', 'desc')
            ->get()
            ->unique('service_key')
            ->values();

        return response()->json([
            'data' => [
                'server_id' => $server->id,
                'services' => $services,
            ],
        ]);
    }
}

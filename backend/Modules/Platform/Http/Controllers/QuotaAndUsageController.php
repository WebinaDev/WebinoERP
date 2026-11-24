<?php

namespace Modules\Platform\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Platform\Entities\PlatformAlertEvent;
use Modules\Platform\Entities\PlatformMetricSnapshot;
use Modules\Platform\Entities\PlatformProject;
use Modules\Platform\Entities\PlatformProjectQuota;
use Modules\Platform\Entities\PlatformResource;
use Modules\Platform\Entities\PlatformResourceQuota;

class QuotaAndUsageController extends Controller
{
    /**
     * GET /api/v1/platform/resources/{id}/metrics
     */
    public function resourceMetrics(Request $request, int $id): JsonResponse
    {
        $resource = PlatformResource::query()->findOrFail($id);

        $from = $request->filled('from') ? Carbon::parse($request->query('from')) : Carbon::now()->subHours(24);
        $to = $request->filled('to') ? Carbon::parse($request->query('to')) : Carbon::now();

        $snapshots = PlatformMetricSnapshot::query()
            ->where('resource_id', $resource->id)
            ->where('scope', 'resource')
            ->whereBetween('collected_at', [$from, $to])
            ->orderBy('collected_at', 'asc')
            ->limit(1000)
            ->get()
            ->map(fn ($s) => [
                'metric_key' => $s->metric_key,
                'value' => (float) $s->value,
                'unit' => $s->unit,
                'labels' => $s->labels,
                'collected_at' => $s->collected_at?->toIso8601String(),
            ]);

        // Also fetch latest values for summary
        $latest = PlatformMetricSnapshot::query()
            ->where('resource_id', $resource->id)
            ->where('scope', 'resource')
            ->orderBy('collected_at', 'desc')
            ->get()
            ->groupBy('metric_key')
            ->map(fn ($g) => (float) $g->first()->value);

        return response()->json([
            'data' => [
                'resource_id' => $resource->id,
                'latest' => [
                    'disk_bytes' => $latest->get('disk.used', 0.0),
                    'inode_count' => (int) $latest->get('inode.used', 0),
                    'cpu_usage' => $latest->get('cpu.usage', 0.0),
                    'mem_bytes' => $latest->get('mem.used', 0.0),
                ],
                'metrics' => $snapshots,
            ],
        ]);
    }

    /**
     * GET /api/v1/platform/resources/{id}/quota
     */
    public function getResourceQuota(int $id): JsonResponse
    {
        $resource = PlatformResource::query()->findOrFail($id);
        $quota = PlatformResourceQuota::query()->firstOrCreate(
            ['resource_id' => $resource->id],
            [
                'cpu_percent_soft' => 80.0,
                'cpu_percent_hard' => 95.0,
                'ram_mb_soft' => 1024.0,
                'ram_mb_hard' => 2048.0,
                'disk_mb_soft' => 5120.0,
                'disk_mb_hard' => 10240.0,
                'inode_soft' => 100000,
                'inode_hard' => 150000,
                'enforce_action' => 'alert',
                'is_enabled' => true,
            ]
        );

        return response()->json(['data' => $quota]);
    }

    /**
     * PUT /api/v1/platform/resources/{id}/quota
     */
    public function updateResourceQuota(Request $request, int $id): JsonResponse
    {
        $resource = PlatformResource::query()->findOrFail($id);

        $data = $request->validate([
            'cpu_percent_soft' => 'nullable|numeric|min:0|max:100',
            'cpu_percent_hard' => 'nullable|numeric|min:0|max:100',
            'ram_mb_soft' => 'nullable|numeric|min:0',
            'ram_mb_hard' => 'nullable|numeric|min:0',
            'disk_mb_soft' => 'nullable|numeric|min:0',
            'disk_mb_hard' => 'nullable|numeric|min:0',
            'inode_soft' => 'nullable|integer|min:0',
            'inode_hard' => 'nullable|integer|min:0',
            'bandwidth_gb_month_soft' => 'nullable|numeric|min:0',
            'bandwidth_gb_month_hard' => 'nullable|numeric|min:0',
            'enforce_action' => 'required|in:alert,throttle,suspend',
            'is_enabled' => 'required|boolean',
        ]);

        $quota = PlatformResourceQuota::query()->updateOrCreate(
            ['resource_id' => $resource->id],
            $data
        );

        return response()->json([
            'data' => $quota,
            'message' => 'سقف مصرف منابع با موفقیت بروزرسانی شد.',
        ]);
    }

    /**
     * GET /api/v1/platform/projects/{id}/usage
     */
    public function projectUsage(int $id): JsonResponse
    {
        $project = PlatformProject::query()->findOrFail($id);

        // Fetch latest aggregated project snapshots
        $latest = PlatformMetricSnapshot::query()
            ->where('project_id', $project->id)
            ->where('scope', 'project')
            ->orderBy('collected_at', 'desc')
            ->get()
            ->groupBy('metric_key')
            ->map(fn ($g) => (float) $g->first()->value);

        // Fetch each resource's latest usage in this project
        $resources = PlatformResource::query()
            ->whereHas('environment', fn ($q) => $q->where('project_id', $project->id))
            ->get()
            ->map(function ($r) {
                $rLatest = PlatformMetricSnapshot::query()
                    ->where('resource_id', $r->id)
                    ->where('scope', 'resource')
                    ->orderBy('collected_at', 'desc')
                    ->get()
                    ->groupBy('metric_key')
                    ->map(fn ($g) => (float) $g->first()->value);

                return [
                    'id' => $r->id,
                    'name' => $r->name,
                    'type' => $r->type,
                    'status' => $r->status,
                    'disk_bytes' => $rLatest->get('disk.used', 0.0),
                    'mem_bytes' => $rLatest->get('mem.used', 0.0),
                    'cpu_usage' => $rLatest->get('cpu.usage', 0.0),
                    'inode_count' => (int) $rLatest->get('inode.used', 0),
                ];
            });

        $quota = PlatformProjectQuota::query()->where('project_id', $project->id)->first();

        return response()->json([
            'data' => [
                'project_id' => $project->id,
                'total_usage' => [
                    'disk_bytes' => $latest->get('project.disk.used', 0.0),
                    'ram_bytes' => $latest->get('project.ram.used', 0.0),
                    'cpu_usage' => $latest->get('project.cpu.usage', 0.0),
                    'inode_count' => (int) $latest->get('project.inode.used', 0),
                ],
                'quota' => $quota,
                'resources' => $resources,
            ],
        ]);
    }

    /**
     * GET /api/v1/platform/projects/{id}/quota
     */
    public function getProjectQuota(int $id): JsonResponse
    {
        $project = PlatformProject::query()->findOrFail($id);
        $quota = PlatformProjectQuota::query()->firstOrCreate(
            ['project_id' => $project->id],
            [
                'cpu_percent_soft' => 150.0,
                'cpu_percent_hard' => 200.0,
                'ram_mb_soft' => 4096.0,
                'ram_mb_hard' => 8192.0,
                'disk_mb_soft' => 20480.0,
                'disk_mb_hard' => 40960.0,
                'inode_soft' => 500000,
                'inode_hard' => 1000000,
                'enforce_action' => 'alert',
                'is_enabled' => true,
            ]
        );

        return response()->json(['data' => $quota]);
    }

    /**
     * PUT /api/v1/platform/projects/{id}/quota
     */
    public function updateProjectQuota(Request $request, int $id): JsonResponse
    {
        $project = PlatformProject::query()->findOrFail($id);

        $data = $request->validate([
            'cpu_percent_soft' => 'nullable|numeric|min:0',
            'cpu_percent_hard' => 'nullable|numeric|min:0',
            'ram_mb_soft' => 'nullable|numeric|min:0',
            'ram_mb_hard' => 'nullable|numeric|min:0',
            'disk_mb_soft' => 'nullable|numeric|min:0',
            'disk_mb_hard' => 'nullable|numeric|min:0',
            'inode_soft' => 'nullable|integer|min:0',
            'inode_hard' => 'nullable|integer|min:0',
            'bandwidth_gb_month_soft' => 'nullable|numeric|min:0',
            'bandwidth_gb_month_hard' => 'nullable|numeric|min:0',
            'enforce_action' => 'required|in:alert,throttle,suspend',
            'is_enabled' => 'required|boolean',
        ]);

        $quota = PlatformProjectQuota::query()->updateOrCreate(
            ['project_id' => $project->id],
            $data
        );

        return response()->json([
            'data' => $quota,
            'message' => 'سقف مصرف تجمیعی پروژه بروزرسانی شد.',
        ]);
    }

    /**
     * GET /api/v1/platform/monitoring/reports/usage
     * Query: from, to, server_id, project_id, resource_id, group_by=hour|day
     */
    public function usageReport(Request $request): JsonResponse
    {
        $from = $request->filled('from') ? Carbon::parse($request->query('from')) : Carbon::now()->subDays(7);
        $to = $request->filled('to') ? Carbon::parse($request->query('to')) : Carbon::now();
        $groupBy = $request->query('group_by', 'day');

        $query = PlatformMetricSnapshot::query()
            ->whereBetween('collected_at', [$from, $to]);

        if ($request->filled('server_id')) {
            $query->where('server_id', $request->query('server_id'));
        }
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->query('project_id'));
        }
        if ($request->filled('resource_id')) {
            $query->where('resource_id', $request->query('resource_id'));
        }

        $dateFormat = $groupBy === 'hour' ? 'Y-m-d H:00:00' : 'Y-m-d 00:00:00';

        $records = $query->select([
            'metric_key',
            'scope',
            DB::raw("DATE_TRUNC('" . ($groupBy === 'hour' ? 'hour' : 'day') . "', collected_at) as bucket"),
            DB::raw('AVG(value) as avg_value'),
            DB::raw('MAX(value) as max_value'),
            DB::raw('MIN(value) as min_value'),
            DB::raw('COUNT(*) as samples'),
        ])
            ->groupBy('metric_key', 'scope', 'bucket')
            ->orderBy('bucket', 'asc')
            ->get();

        return response()->json([
            'data' => [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
                'group_by' => $groupBy,
                'series' => $records,
            ],
        ]);
    }
}

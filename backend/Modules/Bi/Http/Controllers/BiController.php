<?php

namespace Modules\Bi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Bi\Entities\BiSubscription;
use Modules\Bi\Entities\BiThresholdAlert;
use Modules\Bi\Services\BiDataMartService;

class BiController extends Controller
{
    public function kpis(Request $request, BiDataMartService $dataMart): JsonResponse
    {
        $filters = [
            'company_id' => $request->input('company_id'),
            'from' => $request->input('from'),
            'to' => $request->input('to'),
        ];

        return response()->json(['data' => $dataMart->getKpiSummary($filters)]);
    }

    public function dashboard(Request $request, string $role, BiDataMartService $dataMart): JsonResponse
    {
        $filters = [
            'company_id' => $request->input('company_id'),
            'from' => $request->input('from'),
            'to' => $request->input('to'),
        ];

        return response()->json(['data' => $dataMart->getExecutiveDashboard($role, $filters)]);
    }

    public function export(Request $request, BiDataMartService $dataMart): JsonResponse
    {
        $format = $request->input('format', 'json');
        $filters = [
            'company_id' => $request->input('company_id'),
            'from' => $request->input('from'),
            'to' => $request->input('to'),
        ];

        $kpis = $dataMart->getKpiSummary($filters);

        if ($format === 'csv') {
            $csvLines = ["KPI,Metric,Value"];
            foreach ($kpis as $category => $items) {
                foreach ($items as $k => $v) {
                    $csvLines[] = "{$category},{$k},{$v}";
                }
            }
            return response()->json(['data' => [
                'format' => 'csv',
                'content' => implode("\n", $csvLines),
            ]]);
        }

        return response()->json(['data' => [
            'format' => 'json',
            'content' => $kpis,
        ]]);
    }

    public function subscriptions(Request $request): JsonResponse
    {
        $list = BiSubscription::query()->orderByDesc('id')->get();

        return response()->json(['data' => $list]);
    }

    public function storeSubscription(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:160',
            'report_type' => 'required|string|max:48',
            'frequency' => 'required|in:daily,weekly,monthly',
            'format' => 'required|in:csv,json',
            'recipients' => 'required|array|min:1',
            'filters' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $sub = BiSubscription::query()->create([
            'name' => $data['name'],
            'report_type' => $data['report_type'],
            'frequency' => $data['frequency'],
            'format' => $data['format'],
            'recipients' => $data['recipients'],
            'filters' => $data['filters'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $sub], 201);
    }

    public function alerts(Request $request): JsonResponse
    {
        $list = BiThresholdAlert::query()->orderByDesc('id')->get();

        return response()->json(['data' => $list]);
    }

    public function storeAlert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:160',
            'metric_key' => 'required|string|max:48',
            'comparator' => 'required|in:gt,lt,gte,lte,eq',
            'threshold_value' => 'required|numeric',
            'severity' => 'required|in:info,warning,critical',
            'notify_channels' => 'nullable|array',
            'notify_user_id' => 'nullable|exists:users,id',
            'is_active' => 'nullable|boolean',
        ]);

        $alert = BiThresholdAlert::query()->create([
            'title' => $data['title'],
            'metric_key' => $data['metric_key'],
            'comparator' => $data['comparator'],
            'threshold_value' => $data['threshold_value'],
            'severity' => $data['severity'],
            'notify_channels' => $data['notify_channels'] ?? ['in_app'],
            'notify_user_id' => $data['notify_user_id'] ?? $request->user()->id,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json(['data' => $alert], 201);
    }
}

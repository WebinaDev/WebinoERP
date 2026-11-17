<?php

namespace Modules\Assets\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Assets\Entities\AstAsset;
use Modules\Assets\Entities\AstDepreciationRun;
use Modules\Assets\Entities\AstInsurance;
use Modules\Assets\Entities\AstMaintenanceOrder;
use Modules\Assets\Entities\AstMaintenancePlan;
use Modules\Assets\Services\AssetDepreciationService;

class AssetsController extends Controller
{
    public function assets(Request $request): JsonResponse
    {
        $query = AstAsset::query()->with(['company', 'custodian']);
        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json(['data' => $query->orderByDesc('id')->paginate(30)]);
    }

    public function storeAsset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'asset_code' => 'required|string|max:48|unique:ast_assets,asset_code',
            'name' => 'required|string|max:160',
            'category' => 'required|string|max:64',
            'company_id' => 'nullable|exists:crm_companies,id',
            'branch_id' => 'nullable|integer',
            'location' => 'nullable|string|max:160',
            'custodian_id' => 'nullable|exists:hrm_employees,id',
            'purchase_date' => 'nullable|date',
            'purchase_cost' => 'required|numeric|min:0',
            'salvage_value' => 'nullable|numeric|min:0',
            'useful_life_months' => 'nullable|integer|min:1',
            'depreciation_method' => 'nullable|in:straight_line,declining_balance',
            'notes' => 'nullable|string',
        ]);

        $purchaseCost = (float) $data['purchase_cost'];
        $asset = AstAsset::query()->create(array_merge($data, [
            'book_value' => $purchaseCost,
            'accumulated_depreciation' => 0,
            'status' => 'active',
        ]));

        return response()->json(['data' => $asset->load(['company', 'custodian'])], 201);
    }

    public function showAsset(int $id): JsonResponse
    {
        $asset = AstAsset::query()->with(['company', 'custodian', 'maintenancePlans', 'maintenanceOrders', 'insurances'])->findOrFail($id);

        return response()->json(['data' => $asset]);
    }

    public function depreciationRuns(Request $request): JsonResponse
    {
        $query = AstDepreciationRun::query()->with('company');

        return response()->json(['data' => $query->orderByDesc('period_date')->paginate(30)]);
    }

    public function runDepreciation(Request $request, AssetDepreciationService $deprService): JsonResponse
    {
        $data = $request->validate([
            'period_date' => 'required|date',
            'company_id' => 'nullable|exists:crm_companies,id',
        ]);

        $run = $deprService->runPeriodicDepreciation(
            $data['period_date'],
            $data['company_id'] ? (int) $data['company_id'] : null,
            $request->user()
        );

        return response()->json(['data' => $run], 201);
    }

    public function maintenancePlans(Request $request): JsonResponse
    {
        $query = AstMaintenancePlan::query()->with('asset');
        if ($request->filled('asset_id')) {
            $query->where('asset_id', $request->input('asset_id'));
        }

        return response()->json(['data' => $query->orderBy('title')->get()]);
    }

    public function storeMaintenancePlan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'asset_id' => 'required|exists:ast_assets,id',
            'title' => 'required|string|max:160',
            'frequency' => 'nullable|in:daily,weekly,monthly,quarterly,annual',
            'interval_days' => 'required|integer|min:1',
            'next_due_date' => 'nullable|date',
            'checklist' => 'nullable|string',
        ]);

        $plan = AstMaintenancePlan::query()->create($data);

        return response()->json(['data' => $plan->load('asset')], 201);
    }

    public function maintenanceOrders(Request $request): JsonResponse
    {
        $query = AstMaintenanceOrder::query()->with(['asset', 'plan', 'assignee']);
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json(['data' => $query->orderByDesc('scheduled_date')->paginate(30)]);
    }

    public function storeMaintenanceOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'asset_id' => 'required|exists:ast_assets,id',
            'plan_id' => 'nullable|exists:ast_maintenance_plans,id',
            'type' => 'nullable|in:preventive,corrective,overhaul',
            'priority' => 'nullable|in:low,medium,high,critical',
            'scheduled_date' => 'required|date',
            'description' => 'nullable|string',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $orderNumber = 'MO-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(3)));

        $order = AstMaintenanceOrder::query()->create(array_merge($data, [
            'order_number' => $orderNumber,
            'status' => 'scheduled',
            'created_by' => $request->user()->id,
        ]));

        return response()->json(['data' => $order->load(['asset', 'assignee'])], 201);
    }

    public function completeMaintenanceOrder(Request $request, int $id): JsonResponse
    {
        $order = AstMaintenanceOrder::query()->findOrFail($id);
        $data = $request->validate([
            'work_done' => 'required|string',
            'labor_cost' => 'nullable|numeric|min:0',
            'parts_cost' => 'nullable|numeric|min:0',
        ]);

        $labor = (float) ($data['labor_cost'] ?? 0);
        $parts = (float) ($data['parts_cost'] ?? 0);

        $order->update([
            'status' => 'completed',
            'completed_at' => now(),
            'work_done' => $data['work_done'],
            'labor_cost' => $labor,
            'parts_cost' => $parts,
            'total_cost' => $labor + $parts,
        ]);

        if ($order->plan_id && $order->plan) {
            $nextDue = now()->addDays($order->plan->interval_days)->toDateString();
            $order->plan->update([
                'last_performed_at' => now()->toDateString(),
                'next_due_date' => $nextDue,
            ]);
        }

        return response()->json(['data' => $order->fresh()]);
    }

    public function insurances(Request $request): JsonResponse
    {
        $query = AstInsurance::query()->with('asset');
        if ($request->filled('asset_id')) {
            $query->where('asset_id', $request->input('asset_id'));
        }

        return response()->json(['data' => $query->get()]);
    }

    public function storeInsurance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'asset_id' => 'required|exists:ast_assets,id',
            'policy_number' => 'required|string|max:64',
            'insurer_name' => 'required|string|max:160',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'premium_amount' => 'nullable|numeric|min:0',
            'coverage_amount' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $ins = AstInsurance::query()->create($data);

        return response()->json(['data' => $ins->load('asset')], 201);
    }
}

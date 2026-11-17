<?php

namespace Modules\Qm\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Qm\Entities\QmCapa;
use Modules\Qm\Entities\QmInspection;
use Modules\Qm\Entities\QmInspectionPlan;
use Modules\Qm\Entities\QmLotBatch;
use Modules\Qm\Entities\QmNcr;
use Modules\Qm\Services\QualityGateService;

class QmController extends Controller
{
    public function plans(Request $request): JsonResponse
    {
        $query = QmInspectionPlan::query()->with('product');
        if ($request->filled('inspection_type')) {
            $query->where('inspection_type', $request->input('inspection_type'));
        }

        return response()->json(['data' => $query->orderBy('title')->get()]);
    }

    public function storePlan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:160',
            'inspection_type' => 'required|in:incoming,in_process,final,sales_return',
            'product_id' => 'nullable|exists:acc_products,id',
            'criteria' => 'required|array|min:1',
            'instructions' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $plan = QmInspectionPlan::query()->create([
            'title' => $data['title'],
            'inspection_type' => $data['inspection_type'],
            'product_id' => $data['product_id'] ?? null,
            'criteria' => $data['criteria'],
            'instructions' => $data['instructions'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $plan->load('product')], 201);
    }

    public function lotBatches(Request $request): JsonResponse
    {
        $query = QmLotBatch::query()->with('product');
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json(['data' => $query->orderByDesc('id')->paginate(30)]);
    }

    public function storeLotBatch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lot_number' => 'required|string|max:64|unique:qm_lot_batches,lot_number',
            'product_id' => 'required|exists:acc_products,id',
            'quantity' => 'required|numeric|gt:0',
            'manufacture_date' => 'nullable|date',
            'expiry_date' => 'nullable|date',
            'status' => 'nullable|in:quarantined,released,rejected,expired',
            'notes' => 'nullable|string',
        ]);

        $lot = QmLotBatch::query()->create($data);

        return response()->json(['data' => $lot->load('product')], 201);
    }

    public function updateLotStatus(Request $request, int $id, QualityGateService $gate): JsonResponse
    {
        $lot = QmLotBatch::query()->findOrFail($id);
        $data = $request->validate([
            'status' => 'required|in:quarantined,released,rejected,expired',
            'reason' => 'nullable|string',
        ]);

        if ($data['status'] === 'released') {
            $gate->releaseLot($lot);
        } else {
            $gate->quarantineLot($lot, $data['reason'] ?? null);
            if ($data['status'] !== 'quarantined') {
                $lot->update(['status' => $data['status']]);
            }
        }

        return response()->json(['data' => $lot->fresh()]);
    }

    public function inspections(Request $request): JsonResponse
    {
        $query = QmInspection::query()->with(['plan', 'product', 'lot', 'inspector']);
        if ($request->filled('verdict')) {
            $query->where('verdict', $request->input('verdict'));
        }
        if ($request->filled('inspection_type')) {
            $query->where('inspection_type', $request->input('inspection_type'));
        }

        return response()->json(['data' => $query->orderByDesc('id')->paginate(30)]);
    }

    public function storeInspection(Request $request, QualityGateService $gate): JsonResponse
    {
        $data = $request->validate([
            'plan_id' => 'nullable|exists:qm_inspection_plans,id',
            'product_id' => 'required|exists:acc_products,id',
            'lot_id' => 'nullable|exists:qm_lot_batches,id',
            'inspection_type' => 'required|in:incoming,in_process,final,sales_return',
            'reference_id' => 'nullable|integer',
            'sample_size' => 'nullable|numeric|gt:0',
            'results' => 'nullable|array',
            'verdict' => 'required|in:pending,pass,conditional,fail',
            'remarks' => 'nullable|string',
        ]);

        $inspNumber = 'QC-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(3)));

        $inspection = QmInspection::query()->create([
            'inspection_number' => $inspNumber,
            'plan_id' => $data['plan_id'] ?? null,
            'product_id' => $data['product_id'],
            'lot_id' => $data['lot_id'] ?? null,
            'inspection_type' => $data['inspection_type'],
            'reference_id' => $data['reference_id'] ?? null,
            'sample_size' => $data['sample_size'] ?? 1,
            'results' => $data['results'] ?? null,
            'verdict' => $data['verdict'],
            'inspector_id' => $request->user()->id,
            'inspected_at' => now(),
            'remarks' => $data['remarks'] ?? null,
        ]);

        // Quality clearance gate: automatically release lot if pass, or quarantine/reject if fail
        if (! empty($data['lot_id'])) {
            $lot = QmLotBatch::find($data['lot_id']);
            if ($lot) {
                if ($data['verdict'] === 'pass') {
                    $gate->releaseLot($lot);
                } elseif ($data['verdict'] === 'fail') {
                    $gate->quarantineLot($lot, "رد شده در بازرسی کیفیت {$inspNumber}");
                }
            }
        }

        return response()->json(['data' => $inspection->load(['plan', 'product', 'lot'])], 201);
    }

    public function ncrs(Request $request): JsonResponse
    {
        $query = QmNcr::query()->with(['inspection', 'lot', 'product', 'assignee']);
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json(['data' => $query->orderByDesc('id')->paginate(30)]);
    }

    public function storeNcr(Request $request): JsonResponse
    {
        $data = $request->validate([
            'inspection_id' => 'nullable|exists:qm_inspections,id',
            'lot_id' => 'nullable|exists:qm_lot_batches,id',
            'product_id' => 'required|exists:acc_products,id',
            'severity' => 'required|in:minor,major,critical',
            'defect_description' => 'required|string',
            'disposition' => 'nullable|in:scrap,rework,return_to_vendor,accept_as_is',
            'affected_quantity' => 'nullable|numeric|min:0',
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $ncrNumber = 'NCR-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(3)));

        $ncr = QmNcr::query()->create([
            'ncr_number' => $ncrNumber,
            'inspection_id' => $data['inspection_id'] ?? null,
            'lot_id' => $data['lot_id'] ?? null,
            'product_id' => $data['product_id'],
            'severity' => $data['severity'],
            'status' => 'open',
            'defect_description' => $data['defect_description'],
            'disposition' => $data['disposition'] ?? null,
            'affected_quantity' => $data['affected_quantity'] ?? 0,
            'assigned_to' => $data['assigned_to'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $ncr->load(['product', 'lot', 'assignee'])], 201);
    }

    public function capas(Request $request): JsonResponse
    {
        $query = QmCapa::query()->with(['ncr', 'owner']);
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json(['data' => $query->orderByDesc('id')->paginate(30)]);
    }

    public function storeCapa(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ncr_id' => 'nullable|exists:qm_ncrs,id',
            'type' => 'required|in:corrective,preventive',
            'root_cause' => 'required|string',
            'action_plan' => 'required|string',
            'target_date' => 'nullable|date',
            'owner_id' => 'nullable|exists:users,id',
        ]);

        $capaNumber = 'CAPA-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(3)));

        $capa = QmCapa::query()->create([
            'capa_number' => $capaNumber,
            'ncr_id' => $data['ncr_id'] ?? null,
            'type' => $data['type'],
            'root_cause' => $data['root_cause'],
            'action_plan' => $data['action_plan'],
            'status' => 'open',
            'target_date' => $data['target_date'] ?? null,
            'owner_id' => $data['owner_id'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $capa->load(['ncr', 'owner'])], 201);
    }

    public function analytics(Request $request): JsonResponse
    {
        $totalInspections = QmInspection::query()->count();
        $passedCount = QmInspection::query()->where('verdict', 'pass')->count();
        $failedCount = QmInspection::query()->where('verdict', 'fail')->count();
        $openNcrs = QmNcr::query()->where('status', 'open')->count();
        $openCapas = QmCapa::query()->where('status', 'open')->count();
        $quarantinedLots = QmLotBatch::query()->where('status', 'quarantined')->count();

        $passRate = $totalInspections > 0 ? round(($passedCount / $totalInspections) * 100, 1) : 100.0;
        $scrapRate = $totalInspections > 0 ? round(($failedCount / $totalInspections) * 100, 1) : 0.0;

        return response()->json([
            'data' => [
                'total_inspections' => $totalInspections,
                'pass_rate' => $passRate,
                'scrap_rate' => $scrapRate,
                'open_ncrs' => $openNcrs,
                'open_capas' => $openCapas,
                'quarantined_lots' => $quarantinedLots,
            ],
        ]);
    }
}

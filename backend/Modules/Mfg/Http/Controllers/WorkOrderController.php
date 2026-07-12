<?php

namespace Modules\Mfg\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Mfg\Entities\MfgWorkOrder;
use Modules\Mfg\Services\MfgWorkOrderService;

class WorkOrderController extends Controller
{
    use PaginatesApi;

    public function __construct(private MfgWorkOrderService $service) {}

    public function index(Request $request): JsonResponse
    {
        $q = MfgWorkOrder::query()->with(['bom', 'operations'])->orderByDesc('id');
        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }

        return $this->paginatedResponse($q->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'bom_id' => 'nullable|integer|exists:mfg_boms,id',
            'product_id' => 'required|integer|exists:acc_products,id',
            'qty_planned' => 'nullable|numeric|min:0.0001',
            'due_at' => 'nullable|date',
            'warehouse_id' => 'nullable|integer|exists:scm_warehouses,id',
            'operations' => 'nullable|array',
            'operations.*.name' => 'required_with:operations|string|max:191',
            'operations.*.sequence' => 'nullable|integer|min:1',
            'operations.*.duration_minutes' => 'nullable|integer|min:0',
        ]);

        $wo = $this->service->create(
            collect($data)->except('operations')->all(),
            $data['operations'] ?? [],
        );

        return response()->json(['data' => $wo, 'message' => 'Created'], 201);
    }

    public function show(MfgWorkOrder $workOrder): JsonResponse
    {
        return response()->json(['data' => $workOrder->load(['bom.lines', 'operations', 'inspections'])]);
    }

    public function update(Request $request, MfgWorkOrder $workOrder): JsonResponse
    {
        if ($workOrder->status === 'completed') {
            return response()->json(['message' => 'Completed work orders cannot be edited'], 422);
        }

        $data = $request->validate([
            'bom_id' => 'nullable|integer|exists:mfg_boms,id',
            'product_id' => 'sometimes|integer|exists:acc_products,id',
            'qty_planned' => 'nullable|numeric|min:0.0001',
            'due_at' => 'nullable|date',
            'warehouse_id' => 'nullable|integer|exists:scm_warehouses,id',
            'operations' => 'nullable|array',
            'operations.*.name' => 'required_with:operations|string|max:191',
        ]);

        if (array_key_exists('operations', $data)) {
            $this->service->syncOperations($workOrder, $data['operations'] ?? []);
        }
        $workOrder->update(collect($data)->except('operations')->all());

        return response()->json(['data' => $workOrder->fresh()->load(['bom.lines', 'operations'])]);
    }

    public function destroy(MfgWorkOrder $workOrder): JsonResponse
    {
        if ($workOrder->status === 'in_progress') {
            return response()->json(['message' => 'Cannot delete in-progress work order'], 422);
        }
        $workOrder->delete();

        return response()->json(null, 204);
    }

    public function release(MfgWorkOrder $workOrder): JsonResponse
    {
        return response()->json(['data' => $this->service->release($workOrder)]);
    }

    public function start(MfgWorkOrder $workOrder): JsonResponse
    {
        return response()->json(['data' => $this->service->start($workOrder)]);
    }

    public function complete(Request $request, MfgWorkOrder $workOrder): JsonResponse
    {
        $opts = $request->validate([
            'qty_produced' => 'nullable|numeric|min:0',
            'consume_materials' => 'nullable|boolean',
        ]);

        return response()->json(['data' => $this->service->complete($workOrder, $opts)]);
    }

    public function cancel(MfgWorkOrder $workOrder): JsonResponse
    {
        return response()->json(['data' => $this->service->cancel($workOrder)]);
    }
}

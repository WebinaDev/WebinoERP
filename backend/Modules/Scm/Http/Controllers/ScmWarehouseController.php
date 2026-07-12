<?php

namespace Modules\Scm\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Scm\Entities\ScmWarehouse;
use Modules\Scm\Entities\ScmWarehouseDocument;
use Modules\Scm\Entities\ScmWarehouseStock;
use Modules\Scm\Services\WarehouseService;

class ScmWarehouseController extends Controller
{
    use PaginatesApi;

    public function __construct(private WarehouseService $warehouse) {}

    public function warehouses(Request $request): JsonResponse
    {
        $query = ScmWarehouse::query()->orderBy('name');

        return $this->paginatedResponse($query->paginate($this->perPage($request)));
    }

    public function updateWarehouse(Request $request, ScmWarehouse $warehouse): JsonResponse
    {
        $data = $this->warehouse->updateWarehouse(['id' => $warehouse->id, ...$request->all()]);

        return response()->json(['data' => $data]);
    }

    public function destroyWarehouse(ScmWarehouse $warehouse): JsonResponse
    {
        $this->warehouse->deleteWarehouse($warehouse->id);

        return response()->noContent();
    }

    public function stock(Request $request): JsonResponse
    {
        $query = ScmWarehouseStock::query()->with(['warehouse', 'product'])->orderByDesc('updated_at');
        if ($request->filled('filter.warehouse_id')) {
            $query->where('warehouse_id', $request->input('filter.warehouse_id'));
        }

        return $this->paginatedResponse($query->paginate($this->perPage($request)));
    }

    public function stockDetail(int $warehouseId, int $productId): JsonResponse
    {
        return response()->json(['data' => $this->warehouse->stockForProduct($warehouseId, $productId)]);
    }

    public function inbound(Request $request): JsonResponse
    {
        $query = ScmWarehouseDocument::query()->where('type', 'inbound')->orderByDesc('created_at');

        return $this->paginatedResponse($query->paginate($this->perPage($request)));
    }

    public function inboundShow(int $id): JsonResponse
    {
        return response()->json(['data' => $this->warehouse->getDocument($id, 'inbound')]);
    }

    public function storeInbound(Request $request): JsonResponse
    {
        $data = $this->warehouse->createDocument($request->all(), 'inbound', $request->user()?->id);

        return response()->json(['data' => $data, 'message' => 'Inbound draft created'], 201);
    }

    public function postInbound(Request $request): JsonResponse
    {
        $id = (int) $request->validate(['id' => 'required|exists:scm_warehouse_documents,id'])['id'];
        $data = $this->warehouse->postDocument($id, 'inbound', true);

        return response()->json(['data' => $data, 'message' => 'Inbound posted']);
    }

    public function outbound(Request $request): JsonResponse
    {
        $query = ScmWarehouseDocument::query()->where('type', 'outbound')->orderByDesc('created_at');

        return $this->paginatedResponse($query->paginate($this->perPage($request)));
    }

    public function outboundShow(int $id): JsonResponse
    {
        return response()->json(['data' => $this->warehouse->getDocument($id, 'outbound')]);
    }

    public function storeOutbound(Request $request): JsonResponse
    {
        $data = $this->warehouse->createDocument($request->all(), 'outbound', $request->user()?->id);

        return response()->json(['data' => $data, 'message' => 'Outbound draft created'], 201);
    }

    public function postOutbound(Request $request): JsonResponse
    {
        $id = (int) $request->validate(['id' => 'required|exists:scm_warehouse_documents,id'])['id'];
        $data = $this->warehouse->postDocument($id, 'outbound', false);

        return response()->json(['data' => $data, 'message' => 'Outbound posted']);
    }

    public function audit(Request $request): JsonResponse
    {
        $query = ScmWarehouseDocument::query()->where('type', 'audit')->orderByDesc('created_at');

        return $this->paginatedResponse($query->paginate($this->perPage($request)));
    }

    public function auditShow(int $id): JsonResponse
    {
        return response()->json(['data' => $this->warehouse->getDocument($id, 'audit')]);
    }

    public function storeAudit(Request $request): JsonResponse
    {
        $data = $this->warehouse->createAudit($request->all(), $request->user()?->id);

        return response()->json(['data' => $data, 'message' => 'Audit draft created'], 201);
    }

    public function recordAudit(Request $request): JsonResponse
    {
        $data = $this->warehouse->recordAuditItem($request->all());

        return response()->json(['data' => $data]);
    }

    public function completeAudit(Request $request): JsonResponse
    {
        $id = (int) $request->validate(['document_id' => 'required|exists:scm_warehouse_documents,id'])['document_id'];
        $data = $this->warehouse->completeAudit($id);

        return response()->json(['data' => $data]);
    }

    public function postAudit(Request $request): JsonResponse
    {
        $id = (int) $request->validate(['id' => 'required|exists:scm_warehouse_documents,id'])['id'];
        $data = $this->warehouse->postAudit($id);

        return response()->json(['data' => $data, 'message' => 'Audit posted']);
    }

    public function storeWarehouse(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'address' => 'nullable|string',
            'is_default' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);
        $warehouse = ScmWarehouse::create($data);

        return response()->json(['data' => $warehouse, 'message' => 'Warehouse created'], 201);
    }
}

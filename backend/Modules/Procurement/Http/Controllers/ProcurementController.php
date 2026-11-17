<?php

namespace Modules\Procurement\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Procurement\Entities\PrcGoodsReceipt;
use Modules\Procurement\Entities\PrcPurchaseOrder;
use Modules\Procurement\Entities\PrcPurchaseRequest;
use Modules\Procurement\Entities\PrcRfq;
use Modules\Procurement\Services\ProcurementMatchingService;
use Modules\Scm\Services\WarehouseService;

class ProcurementController extends Controller
{
    public function requests(Request $request): JsonResponse
    {
        $query = PrcPurchaseRequest::query()->with(['requester', 'company', 'branch']);
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json(['data' => $query->orderByDesc('id')->paginate(30)]);
    }

    public function storeRequest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'department' => 'nullable|string|max:80',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'required_by_date' => 'nullable|date',
            'reason' => 'nullable|string',
            'company_id' => 'nullable|exists:crm_companies,id',
            'branch_id' => 'nullable|exists:org_branches,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|numeric|gt:0',
            'items.*.estimated_price' => 'nullable|numeric|min:0',
        ]);

        $prNumber = 'PR-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(3)));

        $totalEst = 0;
        foreach ($data['items'] as $it) {
            $totalEst += ((float) $it['quantity']) * ((float) ($it['estimated_price'] ?? 0));
        }

        $pr = PrcPurchaseRequest::query()->create([
            'pr_number' => $prNumber,
            'department' => $data['department'] ?? null,
            'priority' => $data['priority'] ?? 'medium',
            'status' => 'draft',
            'required_by_date' => $data['required_by_date'] ?? null,
            'reason' => $data['reason'] ?? null,
            'company_id' => $data['company_id'] ?? null,
            'branch_id' => $data['branch_id'] ?? null,
            'items' => $data['items'],
            'total_estimated_amount' => $totalEst,
            'requested_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $pr], 201);
    }

    public function rfqs(Request $request): JsonResponse
    {
        $query = PrcRfq::query()->with(['purchaseRequest', 'winnerVendor', 'creator']);

        return response()->json(['data' => $query->orderByDesc('id')->paginate(30)]);
    }

    public function storeRfq(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:160',
            'pr_id' => 'nullable|exists:prc_purchase_requests,id',
            'deadline_date' => 'nullable|date',
            'vendor_ids' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        $rfqNumber = 'RFQ-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(3)));

        $rfq = PrcRfq::query()->create([
            'rfq_number' => $rfqNumber,
            'title' => $data['title'],
            'pr_id' => $data['pr_id'] ?? null,
            'deadline_date' => $data['deadline_date'] ?? null,
            'vendor_ids' => $data['vendor_ids'] ?? [],
            'status' => 'open',
            'notes' => $data['notes'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $rfq], 201);
    }

    public function orders(Request $request): JsonResponse
    {
        $query = PrcPurchaseOrder::query()->with(['vendor', 'purchaseRequest', 'company', 'branch']);
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json(['data' => $query->orderByDesc('id')->paginate(30)]);
    }

    public function storeOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vendor_id' => 'required|exists:acc_persons,id',
            'pr_id' => 'nullable|exists:prc_purchase_requests,id',
            'rfq_id' => 'nullable|exists:prc_rfqs,id',
            'company_id' => 'nullable|exists:crm_companies,id',
            'branch_id' => 'nullable|exists:org_branches,id',
            'order_date' => 'nullable|date',
            'expected_delivery_date' => 'nullable|date',
            'payment_terms' => 'nullable|string|max:64',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|numeric|gt:0',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        $poNumber = 'PO-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(3)));

        $subtotal = 0;
        $taxTotal = 0;
        foreach ($data['items'] as $it) {
            $lineSub = ((float) $it['quantity']) * ((float) $it['unit_price']);
            $lineTax = $lineSub * (((float) ($it['tax_percent'] ?? 0)) / 100);
            $subtotal += $lineSub;
            $taxTotal += $lineTax;
        }

        $po = PrcPurchaseOrder::query()->create([
            'po_number' => $poNumber,
            'vendor_id' => $data['vendor_id'],
            'pr_id' => $data['pr_id'] ?? null,
            'rfq_id' => $data['rfq_id'] ?? null,
            'company_id' => $data['company_id'] ?? null,
            'branch_id' => $data['branch_id'] ?? null,
            'order_date' => $data['order_date'] ?? now()->toDateString(),
            'expected_delivery_date' => $data['expected_delivery_date'] ?? null,
            'status' => 'confirmed',
            'payment_terms' => $data['payment_terms'] ?? 'net30',
            'items' => $data['items'],
            'subtotal' => $subtotal,
            'tax' => $taxTotal,
            'total' => $subtotal + $taxTotal,
            'created_by' => $request->user()->id,
            'confirmed_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $po->load(['vendor', 'company'])], 201);
    }

    public function receipts(Request $request): JsonResponse
    {
        $query = PrcGoodsReceipt::query()->with(['purchaseOrder.vendor', 'warehouse', 'receiver']);

        return response()->json(['data' => $query->orderByDesc('id')->paginate(30)]);
    }

    public function storeReceipt(Request $request, ProcurementMatchingService $matching, WarehouseService $whService): JsonResponse
    {
        $data = $request->validate([
            'po_id' => 'required|exists:prc_purchase_orders,id',
            'warehouse_id' => 'required|exists:scm_warehouses,id',
            'receipt_date' => 'nullable|date',
            'delivery_note_ref' => 'nullable|string|max:80',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.received_qty' => 'required|numeric|gt:0',
        ]);

        $grnNumber = 'GRN-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(3)));

        $grn = PrcGoodsReceipt::query()->create([
            'grn_number' => $grnNumber,
            'po_id' => $data['po_id'],
            'warehouse_id' => $data['warehouse_id'],
            'receipt_date' => $data['receipt_date'] ?? now()->toDateString(),
            'delivery_note_ref' => $data['delivery_note_ref'] ?? null,
            'status' => 'received',
            'items' => $data['items'],
            'received_by' => $request->user()->id,
        ]);

        $grn = $matching->completeGoodsReceipt($grn, $whService);

        return response()->json(['data' => $grn->load(['purchaseOrder', 'warehouse'])], 201);
    }

    public function matchThreeWay(Request $request, ProcurementMatchingService $matching): JsonResponse
    {
        $data = $request->validate([
            'po_id' => 'required|exists:prc_purchase_orders,id',
            'grn_id' => 'required|exists:prc_goods_receipts,id',
            'invoice_id' => 'nullable|integer',
        ]);

        $matchResult = $matching->verifyThreeWayMatch((int) $data['po_id'], (int) $data['grn_id'], $data['invoice_id'] ?? null);

        return response()->json(['data' => $matchResult]);
    }
}

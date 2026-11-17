<?php

namespace Modules\FieldService\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\FieldService\Entities\FsInstallBase;
use Modules\FieldService\Entities\FsServiceOrder;
use Modules\FieldService\Entities\FsSparePartUsed;
use Modules\FieldService\Entities\FsTechnician;
use Modules\FieldService\Services\FieldServiceCostingService;
use Modules\Scm\Services\WarehouseService;

class FieldServiceController extends Controller
{
    public function installBases(Request $request): JsonResponse
    {
        $query = FsInstallBase::query()->with(['account', 'contact', 'product']);
        if ($request->filled('account_id')) {
            $query->where('account_id', $request->input('account_id'));
        }
        if ($request->filled('serial_number')) {
            $query->where('serial_number', 'like', '%'.$request->input('serial_number').'%');
        }

        return response()->json(['data' => $query->orderByDesc('id')->paginate(30)]);
    }

    public function storeInstallBase(Request $request): JsonResponse
    {
        $data = $request->validate([
            'serial_number' => 'required|string|max:64|unique:fs_install_bases,serial_number',
            'device_model' => 'required|string|max:120',
            'account_id' => 'nullable|exists:crm_accounts,id',
            'contact_id' => 'nullable|exists:crm_contacts,id',
            'product_id' => 'nullable|exists:acc_products,id',
            'install_date' => 'nullable|date',
            'warranty_start' => 'nullable|date',
            'warranty_end' => 'nullable|date',
            'status' => 'nullable|in:active,decommissioned,in_repair',
            'installation_address' => 'nullable|string',
            'specs' => 'nullable|array',
        ]);

        $ib = FsInstallBase::query()->create($data);

        return response()->json(['data' => $ib->load(['account', 'contact', 'product'])], 201);
    }

    public function showInstallBase(int $id): JsonResponse
    {
        $ib = FsInstallBase::query()->with(['account', 'contact', 'product', 'serviceOrders.technician'])->findOrFail($id);

        return response()->json([
            'data' => array_merge($ib->toArray(), [
                'is_under_warranty' => $ib->isUnderWarranty(),
            ]),
        ]);
    }

    public function technicians(Request $request): JsonResponse
    {
        $query = FsTechnician::query()->with(['user', 'employee']);
        if ($request->has('is_available')) {
            $query->where('is_available', filter_var($request->input('is_available'), FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json(['data' => $query->orderBy('id')->get()]);
    }

    public function storeTechnician(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'employee_id' => 'nullable|exists:hrm_employees,id',
            'skills' => 'nullable|array',
            'zone' => 'nullable|string|max:80',
            'phone' => 'nullable|string|max:32',
            'is_available' => 'nullable|boolean',
        ]);

        $tech = FsTechnician::query()->create($data);

        return response()->json(['data' => $tech->load(['user', 'employee'])], 201);
    }

    public function orders(Request $request): JsonResponse
    {
        $query = FsServiceOrder::query()->with(['installBase', 'account', 'technician.user', 'spareParts.product']);
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('technician_id')) {
            $query->where('technician_id', $request->input('technician_id'));
        }

        return response()->json(['data' => $query->orderByDesc('id')->paginate(30)]);
    }

    public function storeOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'install_base_id' => 'nullable|exists:fs_install_bases,id',
            'account_id' => 'nullable|exists:crm_accounts,id',
            'ticket_id' => 'nullable|exists:prj_tickets,id',
            'priority' => 'nullable|in:low,medium,high,critical',
            'problem_description' => 'required|string',
            'scheduled_at' => 'nullable|date',
            'sla_response_hours' => 'nullable|integer|min:1',
            'sla_resolution_hours' => 'nullable|integer|min:1',
        ]);

        $respHours = $data['sla_response_hours'] ?? ($data['priority'] === 'critical' ? 2 : 8);
        $resHours = $data['sla_resolution_hours'] ?? ($data['priority'] === 'critical' ? 12 : 48);

        $orderNumber = 'SO-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(3)));

        $order = FsServiceOrder::query()->create([
            'order_number' => $orderNumber,
            'install_base_id' => $data['install_base_id'] ?? null,
            'account_id' => $data['account_id'] ?? null,
            'ticket_id' => $data['ticket_id'] ?? null,
            'priority' => $data['priority'] ?? 'medium',
            'status' => 'requested',
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'sla_response_due' => now()->addHours($respHours),
            'sla_resolution_due' => now()->addHours($resHours),
            'problem_description' => $data['problem_description'],
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $order->load(['installBase', 'account'])], 201);
    }

    public function dispatchOrder(Request $request, int $id): JsonResponse
    {
        $order = FsServiceOrder::query()->findOrFail($id);
        $data = $request->validate([
            'technician_id' => 'required|exists:fs_technicians,id',
            'scheduled_at' => 'nullable|date',
        ]);

        $order->update([
            'technician_id' => $data['technician_id'],
            'scheduled_at' => $data['scheduled_at'] ?? $order->scheduled_at ?? now(),
            'dispatched_at' => now(),
            'status' => 'dispatched',
        ]);

        return response()->json(['data' => $order->fresh()->load(['technician.user'])]);
    }

    public function addSparePart(Request $request, int $id, FieldServiceCostingService $costing): JsonResponse
    {
        $order = FsServiceOrder::query()->with('installBase')->findOrFail($id);
        $data = $request->validate([
            'warehouse_id' => 'nullable|exists:scm_warehouses,id',
            'product_id' => 'required|exists:acc_products,id',
            'quantity' => 'required|numeric|gt:0',
            'unit_cost' => 'required|numeric|min:0',
        ]);

        $isWarranty = $order->installBase ? $order->installBase->isUnderWarranty() : false;
        $total = ((float) $data['quantity']) * ((float) $data['unit_cost']);

        $part = FsSparePartUsed::query()->create([
            'service_order_id' => $order->id,
            'warehouse_id' => $data['warehouse_id'] ?? null,
            'product_id' => $data['product_id'],
            'quantity' => $data['quantity'],
            'unit_cost' => $data['unit_cost'],
            'total_cost' => $total,
            'is_warranty_covered' => $isWarranty,
        ]);

        $costing->recalculateOrderCosts($order->fresh());

        return response()->json(['data' => $part->load('product')], 201);
    }

    public function completeOrder(Request $request, int $id, FieldServiceCostingService $costing, WarehouseService $whService): JsonResponse
    {
        $order = FsServiceOrder::query()->with(['spareParts', 'account', 'installBase']).findOrFail($id);
        $data = $request->validate([
            'resolution_notes' => 'required|string',
            'labor_cost' => 'nullable|numeric|min:0',
        ]);

        $order->update([
            'status' => 'completed',
            'completed_at' => now(),
            'resolution_notes' => $data['resolution_notes'],
            'labor_cost' => $data['labor_cost'] ?? $order->labor_cost,
        ]);

        $costing->recalculateOrderCosts($order);
        $costing->issueStockMovement($order, $whService);
        $invoice = $costing->generateInvoice($order);

        return response()->json([
            'data' => [
                'order' => $order->fresh()->load(['spareParts', 'salesInvoice']),
                'invoice' => $invoice,
            ],
        ]);
    }

    public function reports(Request $request): JsonResponse
    {
        $totalOrders = FsServiceOrder::query()->count();
        $completedOrders = FsServiceOrder::query()->where('status', 'completed')->count();
        $responseBreached = FsServiceOrder::query()->where('sla_response_breached', true)->count();
        $resolutionBreached = FsServiceOrder::query()->where('sla_resolution_breached', true)->count();
        $totalPartsCost = (float) FsSparePartUsed::query()->sum('total_cost');
        $warrantyPartsCost = (float) FsSparePartUsed::query()->where('is_warranty_covered', true)->sum('total_cost');

        return response()->json([
            'data' => [
                'total_orders' => $totalOrders,
                'completed_orders' => $completedOrders,
                'response_sla_breaches' => $responseBreached,
                'resolution_sla_breaches' => $resolutionBreached,
                'compliance_rate' => $totalOrders > 0 ? round((($totalOrders - $resolutionBreached) / $totalOrders) * 100, 1) : 100.0,
                'total_parts_cost' => $totalPartsCost,
                'warranty_covered_cost' => $warrantyPartsCost,
            ],
        ]);
    }
}

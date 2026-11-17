<?php

namespace Modules\Scm\Services;

use Exception;
use Illuminate\Support\Facades\DB;
use Modules\Scm\Entities\ScmWarehouseStock;

/**
 * Enterprise Stock Ledger & Inventory Engine.
 * Guarantees ACID atomic movements, prevents negative stock race conditions,
 * enforces reservation limits, and creates immutable audit ledger trails.
 */
class StockLedgerEngine
{
    /**
     * Record an atomic stock movement (Receipt, Delivery, Transfer, Adjustment).
     *
     * @param  array{
     *   warehouse_id: int,
     *   product_id: int,
     *   location_id?: int|null,
     *   document_id?: int|null,
     *   movement_type: string,
     *   quantity_change: float,
     *   unit_cost?: float,
     *   lot_batch_no?: string|null,
     *   serial_no?: string|null,
     *   user_id?: int|null
     * }  $params
     * @return array<string, mixed>
     */
    public function recordMovement(array $params): array
    {
        return DB::transaction(function () use ($params) {
            $warehouseId = (int) $params['warehouse_id'];
            $productId = (int) $params['product_id'];
            $qtyChange = (float) $params['quantity_change'];

            // 1. قفل کردن سطر موجودی برای جلوگیری از Race Condition
            $stock = ScmWarehouseStock::query()
                ->where('warehouse_id', $warehouseId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            if (! $stock) {
                $stock = ScmWarehouseStock::create([
                    'warehouse_id' => $warehouseId,
                    'product_id' => $productId,
                    'quantity' => 0,
                    'reorder_point' => 10,
                ]);
            }

            $currentQty = (float) $stock->quantity;
            $newQty = $currentQty + $qtyChange;

            // 2. کنترل و جلوگیری از موجودی منفی
            if ($newQty < 0) {
                throw new Exception("موجودی کالا شناسه {$productId} در انبار {$warehouseId} کافی نیست. موجودی فعلی: {$currentQty}، درخواست خروج: " . abs($qtyChange));
            }

            // 3. اعمال تغییر در سطر موجودی انبار
            $stock->update(['quantity' => $newQty]);

            // 4. ثبت ردیف غیرقابل تغییر در دفتر کل حرکات انبار (Immutable Stock Ledger)
            $ledgerId = DB::table('scm_stock_ledger')->insertGetId([
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'location_id' => $params['location_id'] ?? null,
                'document_id' => $params['document_id'] ?? null,
                'movement_type' => $params['movement_type'],
                'quantity_change' => $qtyChange,
                'quantity_after' => $newQty,
                'unit_cost' => (float) ($params['unit_cost'] ?? 0),
                'lot_batch_no' => $params['lot_batch_no'] ?? null,
                'serial_no' => $params['serial_no'] ?? null,
                'created_by' => $params['user_id'] ?? null,
                'recorded_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [
                'ledger_id' => $ledgerId,
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'quantity_previous' => $currentQty,
                'quantity_change' => $qtyChange,
                'quantity_after' => $newQty,
                'recorded_at' => now()->toIso8601String(),
            ];
        });
    }

    /**
     * Reserve inventory for sales order or manufacturing work order.
     */
    public function reserveStock(int $warehouseId, int $productId, float $quantity, string $source, string $referenceNo): int
    {
        return DB::transaction(function () use ($warehouseId, $productId, $quantity, $source, $referenceNo) {
            $stock = ScmWarehouseStock::query()
                ->where('warehouse_id', $warehouseId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            $totalQty = (float) ($stock?->quantity ?? 0);

            // تجمیع رزروهای فعال
            $activeReserved = (float) DB::table('scm_stock_reservations')
                ->where('warehouse_id', $warehouseId)
                ->where('product_id', $productId)
                ->where('status', 'active')
                ->sum('quantity_reserved');

            $availableQty = $totalQty - $activeReserved;
            if ($availableQty < $quantity) {
                throw new Exception("موجودی آزاد جهت رزرو ناکافی است. موجودی آزاد: {$availableQty}، مقدار رزرو درخواستی: {$quantity}");
            }

            return DB::table('scm_stock_reservations')->insertGetId([
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'quantity_reserved' => $quantity,
                'reservation_source' => $source,
                'reference_no' => $referenceNo,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}

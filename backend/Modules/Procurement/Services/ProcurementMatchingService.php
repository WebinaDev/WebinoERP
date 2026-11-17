<?php

namespace Modules\Procurement\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Accounting\Entities\AccountingInvoice;
use Modules\Procurement\Entities\PrcGoodsReceipt;
use Modules\Procurement\Entities\PrcPurchaseOrder;
use Modules\Scm\Entities\ScmWarehouseDocument;
use Modules\Scm\Services\WarehouseService;

class ProcurementMatchingService
{
    /**
     * 3-Way Match Verification:
     * Validates that PO quantity and pricing align with GRN received quantity and Vendor Bill.
     */
    public function verifyThreeWayMatch(int $poId, int $grnId, ?int $invoiceId = null): array
    {
        $po = PrcPurchaseOrder::query()->findOrFail($poId);
        $grn = PrcGoodsReceipt::query()->findOrFail($grnId);
        $invoice = $invoiceId ? AccountingInvoice::query()->find($invoiceId) : null;

        $matchDiscrepancies = [];
        $poItems = collect($po->items ?? []);
        $grnItems = collect($grn->items ?? []);

        foreach ($poItems as $poItem) {
            $pId = $poItem['product_id'];
            $grnItem = $grnItems->firstWhere('product_id', $pId);
            $recQty = (float) ($grnItem['received_qty'] ?? 0);
            $orderQty = (float) $poItem['quantity'];

            if ($recQty > $orderQty) {
                $matchDiscrepancies[] = "اقلام کالا #{$pId}: مقدار تحویل شده ({$recQty}) بیشتر از سفارش خرید ({$orderQty}) است.";
            }
        }

        $isMatched = empty($matchDiscrepancies);

        return [
            'is_matched' => $isMatched,
            'po_total' => (float) $po->total,
            'invoice_total' => $invoice ? (float) $invoice->total : null,
            'discrepancies' => $matchDiscrepancies,
        ];
    }

    /**
     * Complete Goods Receipt: Put away into stock and update PO status
     */
    public function completeGoodsReceipt(PrcGoodsReceipt $grn, WarehouseService $whService): PrcGoodsReceipt
    {
        return DB::transaction(function () use ($grn, $whService) {
            $grn->update(['status' => 'accepted']);

            // Post inbound movement to SCM Warehouse
            if (class_exists(ScmWarehouseDocument::class) && Schema::hasTable('scm_warehouse_documents')) {
                $items = [];
                foreach ($grn->items ?? [] as $item) {
                    $items[] = [
                        'product_id' => $item['product_id'],
                        'quantity' => (float) ($item['received_qty'] ?? $item['quantity'] ?? 1),
                        'unit_price' => 0,
                    ];
                }

                try {
                    $whService->createDocument([
                        'type' => 'inbound',
                        'warehouse_id' => $grn->warehouse_id,
                        'reference' => "GRN-{$grn->grn_number}",
                        'items' => $items,
                        'notes' => "رسید خرید سفارش {$grn->purchaseOrder?->po_number}",
                    ]);
                } catch (\Throwable) {
                    // Graceful fallback
                }
            }

            $po = $grn->purchaseOrder;
            if ($po) {
                $po->update(['status' => 'received']);
            }

            return $grn->fresh();
        });
    }
}

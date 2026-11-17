<?php

namespace Modules\FieldService\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\FieldService\Entities\FsServiceOrder;
use Modules\Sales\Entities\SalesInvoice;
use Modules\Scm\Entities\ScmWarehouseDocument;
use Modules\Scm\Services\WarehouseService;

class FieldServiceCostingService
{
    public function recalculateOrderCosts(FsServiceOrder $order): FsServiceOrder
    {
        $partsCost = (float) $order->spareParts()->where('is_warranty_covered', false)->sum('total_cost');
        $laborCost = (float) $order->labor_cost;
        $total = $partsCost + $laborCost;

        $order->update([
            'parts_cost' => $partsCost,
            'total_cost' => $total,
        ]);

        return $order;
    }

    public function generateInvoice(FsServiceOrder $order): ?SalesInvoice
    {
        if ($order->sales_invoice_id || ! class_exists(SalesInvoice::class) || ! Schema::hasTable('sales_invoices')) {
            return $order->salesInvoice;
        }

        return DB::transaction(function () use ($order) {
            $customerName = $order->account?->name ?? 'مشتری خدمات پس از فروش';
            $invNumber = 'FS-INV-'.str_pad((string) $order->id, 5, '0', STR_PAD_LEFT);

            $items = [
                [
                    'name' => "اجرت خدمات و تعمیرات ({$order->order_number})",
                    'qty' => 1,
                    'price' => (float) $order->labor_cost,
                    'total' => (float) $order->labor_cost,
                ],
            ];

            foreach ($order->spareParts as $part) {
                if (! $part->is_warranty_covered && $part->total_cost > 0) {
                    $items[] = [
                        'name' => "قطعه یدکی: #{$part->product_id}",
                        'qty' => (float) $part->quantity,
                        'price' => (float) $part->unit_cost,
                        'total' => (float) $part->total_cost,
                    ];
                }
            }

            $invoice = SalesInvoice::query()->create([
                'number' => $invNumber,
                'customer_name' => $customerName,
                'total' => $order->total_cost,
                'subtotal' => $order->total_cost,
                'discount' => 0,
                'status' => 'draft',
                'issue_date' => now()->toDateString(),
                'items' => $items,
                'notes' => "صادره از سفارش خدمات پس از فروش {$order->order_number}",
                'created_by' => $order->created_by,
            ]);

            $order->update(['sales_invoice_id' => $invoice->id]);

            return $invoice;
        });
    }

    public function issueStockMovement(FsServiceOrder $order, WarehouseService $whService): void
    {
        if (! class_exists(ScmWarehouseDocument::class) || ! Schema::hasTable('scm_warehouse_documents')) {
            return;
        }

        foreach ($order->spareParts as $part) {
            if ($part->warehouse_id) {
                try {
                    $whService->createDocument([
                        'type' => 'outbound',
                        'warehouse_id' => $part->warehouse_id,
                        'reference' => "FS-ORDER-{$order->order_number}",
                        'items' => [
                            ['product_id' => $part->product_id, 'quantity' => (float) $part->quantity, 'unit_price' => (float) $part->unit_cost],
                        ],
                        'notes' => "مصرف قطعه یدکی در خدمات پس از فروش {$order->order_number}",
                    ]);
                } catch (\Throwable $e) {
                    // Graceful fallback if SCM warehouse document fails
                }
            }
        }
    }
}

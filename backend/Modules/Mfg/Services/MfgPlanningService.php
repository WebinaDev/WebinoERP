<?php

namespace Modules\Mfg\Services;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Entities\AccWarehouseStock;
use Modules\Mfg\Entities\MfgBom;
use Modules\Mfg\Entities\MfgWorkOrder;

class MfgPlanningService
{
    /**
     * @return array{requirements: list<array<string, mixed>>, shortages: list<array<string, mixed>>}
     */
    public function mrp(int $horizonDays = 30): array
    {
        $orders = MfgWorkOrder::query()
            ->with('bom.lines')
            ->whereIn('status', ['draft', 'released', 'in_progress'])
            ->where(function ($q) use ($horizonDays) {
                $q->whereNull('due_at')->orWhere('due_at', '<=', now()->addDays($horizonDays));
            })
            ->get();

        $required = [];
        foreach ($orders as $wo) {
            if (! $wo->bom) {
                continue;
            }
            $qty = (float) $wo->qty_planned - (float) $wo->qty_produced;
            if ($qty <= 0) {
                continue;
            }
            foreach ($wo->bom->lines as $line) {
                $pid = (int) $line->component_product_id;
                $need = (float) $line->quantity * $qty * (1 + ((float) $line->scrap_percent / 100));
                $required[$pid] = ($required[$pid] ?? 0) + $need;
            }
        }

        $requirements = [];
        $shortages = [];
        foreach ($required as $productId => $qty) {
            $available = (float) AccWarehouseStock::query()
                ->where('product_id', $productId)
                ->sum('quantity');

            $row = [
                'product_id' => $productId,
                'required' => round($qty, 4),
                'available' => round($available, 4),
                'shortage' => round(max(0, $qty - $available), 4),
            ];
            $requirements[] = $row;
            if ($row['shortage'] > 0) {
                $shortages[] = $row;
            }
        }

        return [
            'horizon_days' => $horizonDays,
            'open_work_orders' => $orders->count(),
            'active_boms' => MfgBom::query()->where('status', 'active')->count(),
            'requirements' => $requirements,
            'shortages' => $shortages,
        ];
    }

    /**
     * Auto-generate Purchase Requests (PR) from MRP shortages into Procurement module.
     *
     * @return array{generated_pr_count: int, requests: list<array<string, mixed>>}
     */
    public function generatePurchaseRequestsFromShortages(int $horizonDays = 30, ?int $userId = null): array
    {
        $mrp = $this->mrp($horizonDays);
        $shortages = $mrp['shortages'];

        if (empty($shortages) || ! class_exists(\Modules\Procurement\Entities\PrcPurchaseRequest::class)) {
            return ['generated_pr_count' => 0, 'requests' => []];
        }

        $items = [];
        foreach ($shortages as $item) {
            $items[] = [
                'product_id' => $item['product_id'],
                'quantity' => $item['shortage'],
                'uom' => 'unit',
                'estimated_unit_cost' => 0,
                'notes' => 'تولید خودکار از موتور برنامه‌ریزی احتیاجات مواد (MRP)',
            ];
        }

        $prNumber = 'PR-MRP-' . date('Ymd') . '-' . rand(1000, 9999);
        $pr = \Modules\Procurement\Entities\PrcPurchaseRequest::create([
            'pr_number' => $prNumber,
            'requester_id' => $userId,
            'department' => 'برنامه‌ریزی تولید و ساخت',
            'required_date' => now()->addDays($horizonDays),
            'status' => 'submitted',
            'total_estimated_amount' => 0,
            'justification' => 'تأمین کسری مواد اولیه ناشی از محاسبات MRP خط مونتاژ',
            'items' => $items,
        ]);

        return [
            'generated_pr_count' => 1,
            'requests' => [
                [
                    'id' => $pr->id,
                    'pr_number' => $pr->pr_number,
                    'items_count' => count($items),
                    'status' => $pr->status,
                ],
            ],
        ];
    }

}

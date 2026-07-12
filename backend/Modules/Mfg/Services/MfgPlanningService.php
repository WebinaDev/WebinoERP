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
}

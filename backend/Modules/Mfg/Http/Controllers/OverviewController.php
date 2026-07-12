<?php

namespace Modules\Mfg\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Mfg\Entities\MfgBom;
use Modules\Mfg\Entities\MfgQualityInspection;
use Modules\Mfg\Entities\MfgWorkOrder;

class OverviewController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => [
                'boms' => [
                    'total' => MfgBom::query()->count(),
                    'active' => MfgBom::query()->where('status', 'active')->count(),
                ],
                'work_orders' => MfgWorkOrder::query()
                    ->selectRaw('status, count(*) as count')
                    ->groupBy('status')
                    ->pluck('count', 'status'),
                'inspections' => [
                    'open' => MfgQualityInspection::query()->where('status', 'open')->count(),
                    'failed' => MfgQualityInspection::query()->where('result', 'fail')->count(),
                ],
            ],
        ]);
    }
}

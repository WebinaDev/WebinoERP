<?php

namespace Modules\Mfg\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Mfg\Services\MfgPlanningService;

class PlanningController extends Controller
{
    public function __construct(private MfgPlanningService $planning) {}

    public function mrp(Request $request): JsonResponse
    {
        $horizon = max(1, min(365, (int) $request->query('horizon_days', 30)));

        return response()->json(['data' => $this->planning->mrp($horizon)]);
    }
}

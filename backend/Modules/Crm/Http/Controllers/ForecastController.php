<?php

namespace Modules\Crm\Http\Controllers;

use App\Support\MutationAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Crm\Entities\CrmQuota;
use Modules\Crm\Services\CrmForecastService;

class ForecastController extends Controller
{
    public function show(Request $request, CrmForecastService $forecast): JsonResponse
    {
        $data = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        return response()->json([
            'data' => $forecast->report($data['from'] ?? null, $data['to'] ?? null),
        ]);
    }

    public function storeQuota(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'pipeline_id' => 'nullable|exists:crm_pipelines,id',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'amount' => 'required|numeric|min:0',
        ]);
        $quota = CrmQuota::query()->create($data);
        MutationAudit::record($request->user()->id, 'crm', 'quota.create', CrmQuota::class, $quota->id, [
            'amount' => $quota->amount,
            'user_id' => $quota->user_id,
        ]);

        return response()->json(['data' => $quota], 201);
    }

    public function destroyQuota(int $id): JsonResponse
    {
        CrmQuota::query()->findOrFail($id)->delete();

        return response()->json([], 204);
    }
}

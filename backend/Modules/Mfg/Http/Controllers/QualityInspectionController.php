<?php

namespace Modules\Mfg\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Mfg\Entities\MfgQualityInspection;
use Modules\Mfg\Services\MfgQualityService;

class QualityInspectionController extends Controller
{
    use PaginatesApi;

    public function __construct(private MfgQualityService $service) {}

    public function index(Request $request): JsonResponse
    {
        $q = MfgQualityInspection::query()->with('checkItems')->orderByDesc('id');
        if ($request->filled('work_order_id')) {
            $q->where('work_order_id', (int) $request->input('work_order_id'));
        }

        return $this->paginatedResponse($q->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'work_order_id' => 'required|integer|exists:mfg_work_orders,id',
            'type' => 'nullable|string|max:30',
            'check_items' => 'nullable|array',
            'check_items.*.criterion' => 'required_with:check_items|string|max:191',
            'check_items.*.measured_value' => 'nullable|string|max:100',
            'check_items.*.spec_min' => 'nullable|numeric',
            'check_items.*.spec_max' => 'nullable|numeric',
            'check_items.*.passed' => 'nullable|boolean',
        ]);

        $inspection = $this->service->create(
            collect($data)->except('check_items')->all(),
            $data['check_items'] ?? [],
        );

        return response()->json(['data' => $inspection, 'message' => 'Created'], 201);
    }

    public function show(MfgQualityInspection $inspection): JsonResponse
    {
        return response()->json(['data' => $inspection->load(['checkItems', 'workOrder'])]);
    }

    public function update(Request $request, MfgQualityInspection $inspection): JsonResponse
    {
        if ($inspection->status === 'completed') {
            return response()->json(['message' => 'Completed inspections cannot be edited'], 422);
        }

        $data = $request->validate([
            'type' => 'nullable|string|max:30',
            'check_items' => 'nullable|array',
            'check_items.*.criterion' => 'required_with:check_items|string|max:191',
            'check_items.*.measured_value' => 'nullable|string|max:100',
            'check_items.*.spec_min' => 'nullable|numeric',
            'check_items.*.spec_max' => 'nullable|numeric',
            'check_items.*.passed' => 'nullable|boolean',
        ]);

        if (array_key_exists('check_items', $data)) {
            $this->service->syncCheckItems($inspection, $data['check_items'] ?? []);
        }
        $inspection->update(collect($data)->except('check_items')->all());

        return response()->json(['data' => $inspection->fresh()->load('checkItems')]);
    }

    public function destroy(MfgQualityInspection $inspection): JsonResponse
    {
        $inspection->delete();

        return response()->json(null, 204);
    }

    public function complete(MfgQualityInspection $inspection): JsonResponse
    {
        return response()->json(['data' => $this->service->complete($inspection)]);
    }
}

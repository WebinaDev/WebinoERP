<?php

namespace Modules\Hrm\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Hrm\Entities\HrmKpiTemplate;
use Modules\Hrm\Entities\HrmPerformanceCycle;
use Modules\Hrm\Entities\HrmPerformanceReview;

class PerformanceNestedController extends Controller
{
    use PaginatesApi;

    public function kpiTemplatesIndex(Request $request): JsonResponse
    {
        return $this->paginatedResponse(HrmKpiTemplate::query()->orderBy('name')->paginate($this->perPage($request)));
    }

    public function kpiTemplatesStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'description' => 'nullable|string',
            'criteria' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);
        $template = HrmKpiTemplate::create($data);

        return response()->json(['data' => $template, 'message' => 'KPI template saved'], 201);
    }

    public function cyclesIndex(Request $request): JsonResponse
    {
        return $this->paginatedResponse(HrmPerformanceCycle::query()->orderByDesc('start_date')->paginate($this->perPage($request)));
    }

    public function cyclesStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'status' => 'nullable|string|max:20',
        ]);
        $cycle = HrmPerformanceCycle::create($data);

        return response()->json(['data' => $cycle, 'message' => 'Cycle saved'], 201);
    }

    public function reviewsIndex(Request $request): JsonResponse
    {
        return $this->paginatedResponse(HrmPerformanceReview::query()->with('employee')->orderByDesc('created_at')->paginate($this->perPage($request)));
    }

    public function reviewsStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:hrm_employees,id',
            'period' => 'required|string|max:50',
            'score' => 'nullable|integer|min:0|max:100',
            'feedback' => 'nullable|string',
        ]);
        $data['reviewer_id'] = $request->user()->id;
        $review = HrmPerformanceReview::create($data);

        return response()->json(['data' => $review->load('employee'), 'message' => 'Review saved'], 201);
    }
}

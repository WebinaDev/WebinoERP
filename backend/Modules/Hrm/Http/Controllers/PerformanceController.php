<?php

namespace Modules\Hrm\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Hrm\Entities\HrmPerformanceReview;

class PerformanceController extends Controller
{
    use PaginatesApi;

    public function index(Request $request): JsonResponse
    {
        $query = HrmPerformanceReview::query()->with('employee')->orderByDesc('created_at');

        return $this->paginatedResponse($query->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:hrm_employees,id',
            'period' => 'required|string|max:50',
            'score' => 'nullable|integer|min:0|max:100',
            'feedback' => 'nullable|string',
        ]);

        $data['reviewer_id'] = $request->user()->id;
        $review = HrmPerformanceReview::create($data);

        return response()->json(['data' => $review->load('employee'), 'message' => 'Review created'], 201);
    }

    public function update(Request $request, HrmPerformanceReview $review): JsonResponse
    {
        $data = $request->validate([
            'period' => 'sometimes|string|max:50',
            'score' => 'nullable|integer|min:0|max:100',
            'feedback' => 'nullable|string',
        ]);

        $review->update($data);

        return response()->json(['data' => $review->fresh('employee'), 'message' => 'Review updated']);
    }

    public function destroy(HrmPerformanceReview $review): JsonResponse
    {
        $review->delete();

        return response()->noContent();
    }
}

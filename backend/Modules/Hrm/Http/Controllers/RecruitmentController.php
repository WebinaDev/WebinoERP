<?php

namespace Modules\Hrm\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Hrm\Entities\HrmJobPosting;

class RecruitmentController extends Controller
{
    use PaginatesApi;

    public function index(Request $request): JsonResponse
    {
        $query = HrmJobPosting::query()->orderByDesc('created_at');

        if ($request->filled('filter.status')) {
            $query->where('status', $request->input('filter.status'));
        }

        return $this->paginatedResponse($query->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'department' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'status' => 'nullable|string|max:20',
            'closes_at' => 'nullable|date',
        ]);

        $job = HrmJobPosting::create($data);

        return response()->json(['data' => $job, 'message' => 'Job posting created'], 201);
    }

    public function update(Request $request, HrmJobPosting $job): JsonResponse
    {
        $data = $request->validate([
            'title' => 'sometimes|string|max:200',
            'department' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'status' => 'sometimes|string|max:20',
            'closes_at' => 'nullable|date',
        ]);

        $job->update($data);

        return response()->json(['data' => $job->fresh(), 'message' => 'Job posting updated']);
    }

    public function destroy(HrmJobPosting $job): JsonResponse
    {
        $job->delete();

        return response()->noContent();
    }
}

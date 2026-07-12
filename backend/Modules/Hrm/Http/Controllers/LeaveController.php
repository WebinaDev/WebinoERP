<?php

namespace Modules\Hrm\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Hrm\Entities\HrmLeaveRequest;

class LeaveController extends Controller
{
    use PaginatesApi;

    public function index(Request $request): JsonResponse
    {
        $query = HrmLeaveRequest::query()->with('employee')->orderByDesc('created_at');

        if ($request->filled('filter.status')) {
            $query->where('status', $request->input('filter.status'));
        }

        return $this->paginatedResponse($query->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:hrm_employees,id',
            'type' => 'required|string|max:30',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string',
        ]);

        $leave = HrmLeaveRequest::create($data);

        return response()->json(['data' => $leave->load('employee'), 'message' => 'Leave request created'], 201);
    }

    public function update(Request $request, HrmLeaveRequest $leave): JsonResponse
    {
        $data = $request->validate([
            'status' => 'sometimes|string|max:20',
            'type' => 'sometimes|string|max:30',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date',
            'reason' => 'nullable|string',
        ]);

        if (isset($data['status']) && $data['status'] === 'approved') {
            $data['approved_by'] = $request->user()->id;
        }

        $leave->update($data);

        return response()->json(['data' => $leave->fresh('employee'), 'message' => 'Leave updated']);
    }

    public function destroy(HrmLeaveRequest $leave): JsonResponse
    {
        $leave->delete();

        return response()->noContent();
    }
}

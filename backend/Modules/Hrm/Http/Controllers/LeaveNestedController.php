<?php

namespace Modules\Hrm\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Hrm\Entities\HrmLeaveBalance;
use Modules\Hrm\Entities\HrmLeaveRequest;
use Modules\Hrm\Entities\HrmLeaveType;

class LeaveNestedController extends Controller
{
    use PaginatesApi;

    public function typesIndex(Request $request): JsonResponse
    {
        return $this->paginatedResponse(HrmLeaveType::query()->orderBy('name')->paginate($this->perPage($request)));
    }

    public function typesStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'default_days' => 'nullable|integer|min:0',
            'is_paid' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);
        $type = HrmLeaveType::create($data);

        return response()->json(['data' => $type, 'message' => 'Leave type saved'], 201);
    }

    public function requestsIndex(Request $request): JsonResponse
    {
        $query = HrmLeaveRequest::query()->with('employee')->orderByDesc('created_at');
        if ($request->filled('filter.status')) {
            $query->where('status', $request->input('filter.status'));
        }

        return $this->paginatedResponse($query->paginate($this->perPage($request)));
    }

    public function requestStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:hrm_employees,id',
            'type' => 'required|string|max:30',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string',
        ]);
        $leave = HrmLeaveRequest::create([...$data, 'status' => 'pending']);

        return response()->json(['data' => $leave->load('employee'), 'message' => 'Request created'], 201);
    }

    public function requestApprove(Request $request, HrmLeaveRequest $leaveRequest): JsonResponse
    {
        $leaveRequest->update(['status' => 'approved', 'approved_by' => $request->user()->id]);

        return response()->json(['data' => $leaveRequest->fresh('employee'), 'message' => 'Approved']);
    }

    public function requestReject(Request $request, HrmLeaveRequest $leaveRequest): JsonResponse
    {
        $leaveRequest->update(['status' => 'rejected', 'approved_by' => $request->user()->id]);

        return response()->json(['data' => $leaveRequest->fresh('employee'), 'message' => 'Rejected']);
    }

    public function balancesIndex(Request $request): JsonResponse
    {
        $query = HrmLeaveBalance::query()->with(['employee', 'leaveType'])->orderByDesc('year');

        return $this->paginatedResponse($query->paginate($this->perPage($request)));
    }
}

<?php

namespace Modules\Hrm\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\Hrm\Entities\HrmLeaveBalance;
use Modules\Hrm\Entities\HrmLeaveRequest;
use Modules\Hrm\Entities\HrmLeaveType;
use Modules\Hrm\Services\HrmApprovalWorkflow;
use Modules\Hrm\Support\HrmNotifier;

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
        $wf = app(HrmApprovalWorkflow::class);
        $boot = $wf->bootstrapFields();
        // Keep legacy 'pending' if approval columns missing
        if (! Schema::hasColumn('hrm_leave_requests', 'approval_step')) {
            $boot = ['status' => 'pending'];
        }
        $leave = HrmLeaveRequest::create([...$data, ...$boot]);

        return response()->json(['data' => $leave->load('employee'), 'message' => 'Request created'], 201);
    }

    public function requestApprove(Request $request, HrmLeaveRequest $leaveRequest): JsonResponse
    {
        $wf = app(HrmApprovalWorkflow::class);
        if (Schema::hasColumn('hrm_leave_requests', 'approval_step')) {
            // Migrate legacy pending → step 1
            if ($leaveRequest->status === 'pending') {
                $leaveRequest->update([
                    'status' => $wf->initialStatus(),
                    'approval_step' => 1,
                    'current_role' => $wf->currentRoleForStep(1),
                ]);
            }
            $status = $wf->approveModel($leaveRequest, $request->user());
        } else {
            $leaveRequest->update(['status' => 'approved', 'approved_by' => $request->user()->id]);
            $status = 'approved';
        }

        if ($status === HrmApprovalWorkflow::STATUS_APPROVED) {
            $leaveRequest->update(['approved_by' => $request->user()->id]);
            HrmNotifier::notify(
                $leaveRequest->employee_id,
                'leave_approved',
                'مرخصی تأیید شد',
                'درخواست مرخصی leave:'.$leaveRequest->id.' از '.$leaveRequest->start_date.' تأیید شد.'
            );
        }

        return response()->json(['data' => $leaveRequest->fresh('employee'), 'message' => $status === 'approved' ? 'Approved' : 'Advanced']);
    }

    public function requestReject(Request $request, HrmLeaveRequest $leaveRequest): JsonResponse
    {
        $data = $request->validate(['reason' => 'nullable|string|max:2000']);
        $wf = app(HrmApprovalWorkflow::class);
        if (Schema::hasColumn('hrm_leave_requests', 'approval_step')) {
            if ($leaveRequest->status === 'pending') {
                $leaveRequest->update([
                    'status' => $wf->initialStatus(),
                    'approval_step' => 1,
                    'current_role' => $wf->currentRoleForStep(1),
                ]);
            }
            $wf->rejectModel($leaveRequest, $request->user(), $data['reason'] ?? null);
        } else {
            $leaveRequest->update(['status' => 'rejected', 'approved_by' => $request->user()->id]);
        }

        return response()->json(['data' => $leaveRequest->fresh('employee'), 'message' => 'Rejected']);
    }

    public function balancesIndex(Request $request): JsonResponse
    {
        $query = HrmLeaveBalance::query()->with(['employee', 'leaveType'])->orderByDesc('year');

        return $this->paginatedResponse($query->paginate($this->perPage($request)));
    }
}

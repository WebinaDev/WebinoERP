<?php

namespace Modules\Hrm\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Hrm\Entities\HrmTimesheet;
use Modules\Hrm\Services\HrmApprovalWorkflow;
use Modules\Hrm\Support\HrmAccess;

class TimesheetController extends Controller
{
    use PaginatesApi;

    public function index(Request $request): JsonResponse
    {
        $q = HrmTimesheet::query()->with('employee')->orderByDesc('work_date');
        if (! HrmAccess::seesAllStaff()) {
            $q->where('employee_id', HrmAccess::employee()?->id ?? 0);
        } elseif ($request->filled('employee_id')) {
            $q->where('employee_id', $request->integer('employee_id'));
        }
        if ($request->filled('project_id')) {
            $q->where('project_id', $request->integer('project_id'));
        }
        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }

        return $this->paginatedResponse($q->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        if (! HrmAccess::canManageStaff()) {
            $own = HrmAccess::employee();
            abort_unless($own, 403);
            $data['employee_id'] = $own->id;
        }
        $this->assertProject($data);
        $row = HrmTimesheet::query()->create($data + ['status' => 'draft']);

        return response()->json(['data' => $row->load('employee'), 'message' => 'Timesheet saved'], 201);
    }

    public function submit(HrmTimesheet $timesheet): JsonResponse
    {
        $this->assertOwnOrManager($timesheet);
        abort_unless(in_array($timesheet->status, ['draft', 'rejected'], true), 422);
        $wf = app(HrmApprovalWorkflow::class);
        if ($wf->hasCustomFlow('timesheet') && Schema::hasColumn('hrm_timesheets', 'approval_step')) {
            $timesheet->update($wf->bootstrapFields('timesheet') + ['submitted_at' => now()]);
        } else {
            $timesheet->update(['status' => 'submitted', 'submitted_at' => now()]);
        }

        return response()->json(['data' => $timesheet]);
    }

    public function decide(Request $request, HrmTimesheet $timesheet): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:approved,rejected',
            'decision_note' => 'nullable|string',
        ]);
        $wf = app(HrmApprovalWorkflow::class);
        if ($wf->hasCustomFlow('timesheet') && Schema::hasColumn('hrm_timesheets', 'approval_step') && $timesheet->approval_step) {
            $user = $request->user();
            abort_unless($user, 403);
            if ($data['status'] === 'approved') {
                $wf->approveModel($timesheet, $user);
            } else {
                $wf->rejectModel($timesheet, $user, $data['decision_note'] ?? null);
            }
            $timesheet->refresh();
            if (in_array($timesheet->status, ['approved', 'rejected'], true)) {
                $timesheet->update([
                    'decision_note' => $data['decision_note'] ?? $timesheet->decision_note,
                    'approved_by' => $user->id,
                    'approved_at' => now(),
                ]);
            }
        } else {
            abort_unless(HrmAccess::canManageStaff(), 403);
            $timesheet->update([
                'status' => $data['status'],
                'decision_note' => $data['decision_note'] ?? null,
                'approved_by' => $request->user()?->id,
                'approved_at' => now(),
            ]);
        }

        return response()->json(['data' => $timesheet]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'employee_id' => 'nullable|exists:hrm_employees,id',
            'project_id' => 'nullable|integer',
            'task_id' => 'nullable|integer',
            'project_name' => 'nullable|string|max:200',
            'task_name' => 'nullable|string|max:200',
            'work_date' => 'required|date',
            'hours' => 'required|numeric|min:0.25|max:24',
            'description' => 'nullable|string',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertProject(array $data): void
    {
        if (! empty($data['project_id']) && Schema::hasTable('prj_projects')) {
            abort_unless(DB::table('prj_projects')->where('id', $data['project_id'])->exists(), 422, 'Unknown project');
        }
        if (! empty($data['task_id']) && Schema::hasTable('prj_tasks')) {
            abort_unless(DB::table('prj_tasks')->where('id', $data['task_id'])->exists(), 422, 'Unknown task');
        }
    }

    private function assertOwnOrManager(HrmTimesheet $timesheet): void
    {
        if (HrmAccess::canManageStaff()) {
            return;
        }
        abort_unless(HrmAccess::employee()?->id === $timesheet->employee_id, 403);
    }
}

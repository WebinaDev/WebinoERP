<?php

namespace Modules\Hrm\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Hrm\Entities\HrmShiftAssignment;
use Modules\Hrm\Entities\HrmShiftRotation;
use Modules\Hrm\Entities\HrmShiftTemplate;
use Modules\Hrm\Services\HrmShiftPlannerService;
use Modules\Hrm\Support\HrmAccess;
use Modules\Hrm\Support\HrmNotifier;

class ShiftPlanningController extends Controller
{
    use PaginatesApi;

    public function templatesIndex(Request $request): JsonResponse
    {
        return $this->paginatedResponse(HrmShiftTemplate::query()->orderBy('name')->paginate($this->perPage($request)));
    }

    public function templatesStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'grace_minutes' => 'nullable|integer|min:0|max:180',
            'is_active' => 'nullable|boolean',
        ]);
        $row = HrmShiftTemplate::query()->create($data);

        return response()->json(['data' => $row, 'message' => 'Shift template saved'], 201);
    }

    public function templatesDestroy(HrmShiftTemplate $shiftTemplate): JsonResponse
    {
        $shiftTemplate->delete();

        return response()->noContent();
    }

    public function rotationsIndex(Request $request): JsonResponse
    {
        return $this->paginatedResponse(HrmShiftRotation::query()->orderBy('name')->paginate($this->perPage($request)));
    }

    public function rotationsStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'cycle_length' => 'nullable|integer|min:1|max:60',
            'pattern' => 'required|array|min:1',
            'pattern.*.shift_template_id' => 'nullable|exists:hrm_shift_templates,id',
            'pattern.*.off' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
        ]);
        $data['cycle_length'] = $data['cycle_length'] ?? count($data['pattern']);
        $row = HrmShiftRotation::query()->create($data);

        return response()->json(['data' => $row, 'message' => 'Rotation saved'], 201);
    }

    public function applyRotation(Request $request, HrmShiftRotation $rotation, HrmShiftPlannerService $planner): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:hrm_employees,id',
            'start_date' => 'required|date',
            'days' => 'required|integer|min:1|max:366',
            'force' => 'nullable|boolean',
        ]);
        $result = $planner->applyRotation(
            $rotation,
            (int) $data['employee_id'],
            $data['start_date'],
            (int) $data['days'],
            $request->user()?->id,
            (bool) ($data['force'] ?? false)
        );

        HrmNotifier::notify(
            (int) $request->input('employee_id'),
            'shift_assigned',
            'تخصیص شیفت',
            'چرخش شیفت از '.$request->input('start_date').' اعمال شد.'
        );

        return response()->json(['data' => $result, 'message' => 'Rotation applied']);
    }

    public function assignmentsStore(Request $request, HrmShiftPlannerService $planner): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:hrm_employees,id',
            'work_date' => 'required|date',
            'shift_template_id' => 'nullable|exists:hrm_shift_templates,id',
            'is_off' => 'nullable|boolean',
            'notes' => 'nullable|string',
            'force' => 'nullable|boolean',
        ]);
        $isOff = (bool) ($data['is_off'] ?? false);
        if (! $isOff && empty($data['shift_template_id'])) {
            return response()->json(['message' => 'shift_template_id is required'], 422);
        }
        $result = $planner->assign(
            (int) $data['employee_id'],
            $data['work_date'],
            isset($data['shift_template_id']) ? (int) $data['shift_template_id'] : null,
            $isOff,
            null,
            'manual',
            $data['notes'] ?? null,
            $request->user()?->id,
            (bool) ($data['force'] ?? false)
        );

        $assignment = $result['assignment']->load(['employee', 'template']);
        $label = $assignment->template?->name ?? ($assignment->is_off ? 'OFF' : '');
        HrmNotifier::notify(
            (int) $data['employee_id'],
            'shift_assigned',
            'تخصیص شیفت',
            'شیفت '.$label.' برای '.$data['work_date'].' ثبت شد.'
        );

        return response()->json([
            'data' => $assignment,
            'conflicts' => $result['conflicts'],
            'message' => 'Assignment saved',
        ], 201);
    }

    public function calendar(Request $request): JsonResponse
    {
        $from = Carbon::parse($request->input('from', now()->startOfMonth()->toDateString()))->toDateString();
        $to = Carbon::parse($request->input('to', now()->endOfMonth()->toDateString()))->toDateString();
        $query = HrmShiftAssignment::query()
            ->with(['employee:id,first_name,last_name,employee_code', 'template:id,name,start_time,end_time'])
            ->whereBetween('work_date', [$from, $to])
            ->orderBy('work_date');
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }
        if (! HrmAccess::seesAllStaff()) {
            $own = HrmAccess::employee();
            $query->where('employee_id', $own?->id ?? 0);
        }

        return response()->json(['data' => $query->get()]);
    }

    public function conflicts(Request $request, HrmShiftPlannerService $planner): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:hrm_employees,id',
            'work_date' => 'required|date',
        ]);
        if (! HrmAccess::seesAllStaff()) {
            $own = HrmAccess::employee();
            abort_unless($own && $own->id === (int) $data['employee_id'], 403);
        }

        return response()->json(['data' => $planner->conflicts((int) $data['employee_id'], $data['work_date'])]);
    }
}

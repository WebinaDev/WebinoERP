<?php

namespace Modules\Hrm\Services;

use Carbon\Carbon;
use Modules\Hrm\Entities\HrmAttendanceRecord;
use Modules\Hrm\Entities\HrmLeaveRequest;
use Modules\Hrm\Entities\HrmShiftAssignment;
use Modules\Hrm\Entities\HrmShiftRotation;

class HrmShiftPlannerService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function conflicts(int $employeeId, string $date): array
    {
        $out = [];
        $leave = HrmLeaveRequest::query()
            ->where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->first();
        if ($leave) {
            $out[] = [
                'type' => 'leave',
                'date' => $date,
                'leave_id' => $leave->id,
                'leave_type' => $leave->type,
            ];
        }

        $attendance = HrmAttendanceRecord::query()
            ->where('employee_id', $employeeId)
            ->whereDate('date', $date)
            ->whereIn('status', ['leave', 'on_leave', 'absent'])
            ->first();
        if ($attendance) {
            $out[] = [
                'type' => 'attendance',
                'date' => $date,
                'status' => $attendance->status,
            ];
        }

        return $out;
    }

    /**
     * @return array{assignment: HrmShiftAssignment, conflicts: list<array<string, mixed>>}
     */
    public function assign(
        int $employeeId,
        string $date,
        ?int $templateId,
        bool $isOff,
        ?int $rotationId,
        string $source,
        ?string $notes,
        ?int $userId,
        bool $force
    ): array {
        $conflicts = $this->conflicts($employeeId, $date);
        if ($conflicts !== [] && ! $force) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(response()->json([
                'message' => 'Shift conflicts with leave or attendance',
                'errors' => ['conflicts' => $conflicts],
            ], 422));
        }

        $assignment = HrmShiftAssignment::query()->updateOrCreate(
            ['employee_id' => $employeeId, 'work_date' => $date],
            [
                'shift_template_id' => $isOff ? null : $templateId,
                'rotation_id' => $rotationId,
                'source' => $source,
                'is_off' => $isOff,
                'notes' => $notes,
                'created_by' => $userId,
            ]
        );

        return ['assignment' => $assignment, 'conflicts' => $conflicts];
    }

    /**
     * @return array{created: int, skipped: list<array<string, mixed>>}
     */
    public function applyRotation(HrmShiftRotation $rotation, int $employeeId, string $startDate, int $days, ?int $userId, bool $force): array
    {
        $pattern = $rotation->pattern ?? [];
        $cycle = max(1, (int) ($rotation->cycle_length ?: count($pattern) ?: 1));
        $start = Carbon::parse($startDate)->startOfDay();
        $created = 0;
        $skipped = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i)->toDateString();
            $slot = $pattern[$i % $cycle] ?? $pattern[$i % max(1, count($pattern))] ?? null;
            $isOff = (bool) ($slot['off'] ?? $slot['is_off'] ?? false);
            $templateId = isset($slot['shift_template_id']) ? (int) $slot['shift_template_id'] : null;
            $conflicts = $this->conflicts($employeeId, $date);
            if ($conflicts !== [] && ! $force) {
                $skipped[] = ['date' => $date, 'conflicts' => $conflicts];
                continue;
            }
            $this->assign($employeeId, $date, $templateId, $isOff || ! $templateId, $rotation->id, 'rotation', null, $userId, true);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }
}

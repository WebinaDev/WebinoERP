<?php

namespace Modules\Hrm\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Hrm\Entities\HrmAttendanceRecord;
use Modules\Hrm\Entities\HrmEmployee;

class AttendanceNestedController extends Controller
{
    public function checkIn(Request $request): JsonResponse
    {
        $employeeId = $this->resolveEmployeeId($request);
        $data = $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);
        $today = now()->toDateString();
        $record = $this->todayRecord($employeeId, $today, ['status' => 'present']);
        $record->update([
            'check_in' => now()->format('H:i:s'),
            'status' => 'present',
            'notes' => $data['notes'] ?? $record->notes,
        ]);

        return response()->json(['data' => $record->fresh('employee'), 'message' => __('messages.checked_in')]);
    }

    public function checkOut(Request $request): JsonResponse
    {
        $employeeId = $this->resolveEmployeeId($request);
        $today = now()->toDateString();
        $record = $this->todayRecord($employeeId, $today, ['status' => 'present', 'check_in' => now()->format('H:i:s')]);
        if (! $record->check_in) {
            $record->check_in = now()->format('H:i:s');
        }
        $record->check_out = now()->format('H:i:s');
        $record->save();

        return response()->json(['data' => $record->fresh('employee'), 'message' => __('messages.checked_out')]);
    }

    /**
     * `date` is cast to a datetime, so firstOrCreate(['date' => 'Y-m-d']) misses the stored
     * row on drivers that keep the time part and then violates the (employee_id, date) unique key.
     *
     * @param  array<string, mixed>  $defaults
     */
    private function todayRecord(int $employeeId, string $today, array $defaults): HrmAttendanceRecord
    {
        $record = HrmAttendanceRecord::query()
            ->where('employee_id', $employeeId)
            ->whereDate('date', $today)
            ->first();

        return $record ?? HrmAttendanceRecord::query()->create(
            ['employee_id' => $employeeId, 'date' => $today] + $defaults
        );
    }

    private function resolveEmployeeId(Request $request): int
    {
        if ($request->filled('employee_id')) {
            $data = $request->validate([
                'employee_id' => 'required|integer|exists:hrm_employees,id',
            ]);

            return (int) $data['employee_id'];
        }

        $userId = $request->user()?->id;
        $employee = $userId
            ? HrmEmployee::query()->where('user_id', $userId)->first()
            : null;
        if (! $employee && $request->user()?->email) {
            $employee = HrmEmployee::query()->where('email', $request->user()->email)->first();
        }
        if (! $employee) {
            throw ValidationException::withMessages([
                'employee_id' => __('messages.attendance_select_employee_first'),
            ]);
        }

        return (int) $employee->id;
    }
}

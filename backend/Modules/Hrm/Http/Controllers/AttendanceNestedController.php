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
        $record = HrmAttendanceRecord::query()->firstOrCreate(
            ['employee_id' => $employeeId, 'date' => $today],
            ['status' => 'present']
        );
        $record->update([
            'check_in' => now()->format('H:i:s'),
            'status' => 'present',
            'notes' => $data['notes'] ?? $record->notes,
        ]);

        return response()->json(['data' => $record->fresh('employee'), 'message' => 'Checked in']);
    }

    public function checkOut(Request $request): JsonResponse
    {
        $employeeId = $this->resolveEmployeeId($request);
        $today = now()->toDateString();
        $record = HrmAttendanceRecord::query()->firstOrCreate(
            ['employee_id' => $employeeId, 'date' => $today],
            ['status' => 'present', 'check_in' => now()->format('H:i:s')]
        );
        if (! $record->check_in) {
            $record->check_in = now()->format('H:i:s');
        }
        $record->check_out = now()->format('H:i:s');
        $record->save();

        return response()->json(['data' => $record->fresh('employee'), 'message' => 'Checked out']);
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
                'employee_id' => 'Select an employee, or link this login to a staff record, before check-in.',
            ]);
        }

        return (int) $employee->id;
    }
}

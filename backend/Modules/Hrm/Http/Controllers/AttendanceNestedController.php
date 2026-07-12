<?php

namespace Modules\Hrm\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Hrm\Entities\HrmAttendanceRecord;
use Modules\Hrm\Entities\HrmEmployee;

class AttendanceNestedController extends Controller
{
    public function checkIn(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:hrm_employees,id',
            'notes' => 'nullable|string',
        ]);
        $today = now()->toDateString();
        $record = HrmAttendanceRecord::query()->firstOrCreate(
            ['employee_id' => $data['employee_id'], 'date' => $today],
            ['status' => 'present']
        );
        $record->update([
            'check_in' => now()->format('H:i:s'),
            'notes' => $data['notes'] ?? $record->notes,
        ]);

        return response()->json(['data' => $record->load('employee'), 'message' => 'Checked in']);
    }

    public function checkOut(Request $request): JsonResponse
    {
        $data = $request->validate(['employee_id' => 'required|exists:hrm_employees,id']);
        $today = now()->toDateString();
        $record = HrmAttendanceRecord::query()
            ->where('employee_id', $data['employee_id'])
            ->where('date', $today)
            ->firstOrFail();
        $record->update(['check_out' => now()->format('H:i:s')]);

        return response()->json(['data' => $record->load('employee'), 'message' => 'Checked out']);
    }
}

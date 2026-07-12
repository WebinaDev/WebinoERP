<?php

namespace Modules\Hrm\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Hrm\Entities\HrmAttendanceRecord;

class AttendanceController extends Controller
{
    use PaginatesApi;

    public function index(Request $request): JsonResponse
    {
        $query = HrmAttendanceRecord::query()->with('employee')->orderByDesc('date');

        if ($request->filled('filter.employee_id')) {
            $query->where('employee_id', $request->input('filter.employee_id'));
        }

        return $this->paginatedResponse($query->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:hrm_employees,id',
            'date' => 'required|date',
            'check_in' => 'nullable|date_format:H:i',
            'check_out' => 'nullable|date_format:H:i',
            'status' => 'nullable|string|max:20',
            'notes' => 'nullable|string',
        ]);

        $record = HrmAttendanceRecord::updateOrCreate(
            ['employee_id' => $data['employee_id'], 'date' => $data['date']],
            $data
        );

        return response()->json(['data' => $record->load('employee'), 'message' => 'Attendance saved'], 201);
    }

    public function update(Request $request, HrmAttendanceRecord $attendance): JsonResponse
    {
        $data = $request->validate([
            'check_in' => 'nullable|date_format:H:i',
            'check_out' => 'nullable|date_format:H:i',
            'status' => 'nullable|string|max:20',
            'notes' => 'nullable|string',
        ]);

        $attendance->update($data);

        return response()->json(['data' => $attendance->fresh('employee'), 'message' => 'Attendance updated']);
    }

    public function destroy(HrmAttendanceRecord $attendance): JsonResponse
    {
        $attendance->delete();

        return response()->noContent();
    }
}

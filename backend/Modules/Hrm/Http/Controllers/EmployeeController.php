<?php

namespace Modules\Hrm\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Hrm\Entities\HrmEmployee;

class EmployeeController extends Controller
{
    use PaginatesApi;

    public function index(Request $request): JsonResponse
    {
        $query = HrmEmployee::query();
        $paginator = $this->applyIndexQuery(
            $query,
            $request,
            ['status' => 'status', 'department' => 'department'],
            ['first_name', 'last_name', 'employee_code', 'email'],
            ['created_at', 'employee_code', 'last_name'],
        );

        return $this->paginatedResponse($paginator);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_code' => 'required|string|max:50|unique:hrm_employees,employee_code',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'nullable|email|max:150',
            'mobile' => 'nullable|string|max:20',
            'department' => 'nullable|string|max:100',
            'position' => 'nullable|string|max:100',
            'hire_date' => 'nullable|date',
            'status' => 'nullable|string|max:20',
            'base_salary' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $data['created_by'] = $request->user()->id;
        $employee = HrmEmployee::create($data);

        return response()->json(['data' => $employee, 'message' => 'Employee created'], 201);
    }

    public function show(HrmEmployee $employee): JsonResponse
    {
        $employee->load(['user', 'attendanceRecords', 'leaveRequests']);

        return response()->json(['data' => $employee]);
    }

    public function update(Request $request, HrmEmployee $employee): JsonResponse
    {
        $data = $request->validate([
            'employee_code' => 'sometimes|string|max:50|unique:hrm_employees,employee_code,'.$employee->id,
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'email' => 'nullable|email|max:150',
            'mobile' => 'nullable|string|max:20',
            'department' => 'nullable|string|max:100',
            'position' => 'nullable|string|max:100',
            'hire_date' => 'nullable|date',
            'status' => 'nullable|string|max:20',
            'base_salary' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $employee->update($data);

        return response()->json(['data' => $employee->fresh(), 'message' => 'Employee updated']);
    }

    public function destroy(HrmEmployee $employee): JsonResponse
    {
        $employee->delete();

        return response()->noContent();
    }
}

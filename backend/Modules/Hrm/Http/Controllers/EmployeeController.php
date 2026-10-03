<?php

namespace Modules\Hrm\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Services\HrmCompensationRules;

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
            'contract_type' => 'nullable|string|max:40',
            'contract_end_date' => 'nullable|date',
            'contract_status' => 'nullable|string|max:30',

            'engagement_type' => 'nullable|in:full_time,part_time,contractor,freelance,project,remote',
            'pay_basis' => 'nullable|in:monthly,daily,hourly,project_fee',
            'project_fee' => 'nullable|numeric|min:0',
            'daily_rate' => 'nullable|numeric|min:0',
            'hourly_rate' => 'nullable|numeric|min:0',
            'insurance_applicable' => 'nullable|boolean',
            'tax_applicable' => 'nullable|boolean',
            'status' => 'nullable|string|max:20',
            'base_salary' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $data['created_by'] = $request->user()->id;
        $data = app(HrmCompensationRules::class)->applyDefaults($data);
        app(HrmCompensationRules::class)->assertEmployee(new HrmEmployee($data));
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
            'contract_type' => 'nullable|string|max:40',
            'contract_end_date' => 'nullable|date',
            'contract_status' => 'nullable|string|max:30',

            'engagement_type' => 'nullable|in:full_time,part_time,contractor,freelance,project,remote',
            'pay_basis' => 'nullable|in:monthly,daily,hourly,project_fee',
            'project_fee' => 'nullable|numeric|min:0',
            'daily_rate' => 'nullable|numeric|min:0',
            'hourly_rate' => 'nullable|numeric|min:0',
            'insurance_applicable' => 'nullable|boolean',
            'tax_applicable' => 'nullable|boolean',
            'status' => 'nullable|string|max:20',
            'base_salary' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $merged = array_merge($employee->only([
            'engagement_type', 'pay_basis', 'base_salary', 'project_fee', 'daily_rate', 'hourly_rate',
            'insurance_applicable', 'tax_applicable',
        ]), $data);
        $merged = app(HrmCompensationRules::class)->applyDefaults($merged);
        $data = array_merge($data, array_intersect_key($merged, array_flip([
            'engagement_type', 'pay_basis', 'insurance_applicable', 'tax_applicable',
        ])));
        app(HrmCompensationRules::class)->assertEmployee(new HrmEmployee(array_merge($employee->toArray(), $data)));
        $employee->update($data);

        return response()->json(['data' => $employee->fresh(), 'message' => 'Employee updated']);
    }

    public function destroy(HrmEmployee $employee): \Illuminate\Http\Response
    {
        $employee->delete();

        return response()->noContent();
    }
}

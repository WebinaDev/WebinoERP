<?php

namespace Modules\Hrm\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmEmployeeSalary;
use Modules\Hrm\Entities\HrmPayrollComponent;
use Modules\Hrm\Entities\HrmPayrollItem;
use Modules\Hrm\Entities\HrmPayrollRun;
use Modules\Hrm\Entities\HrmPayrollSetting;

class PayrollNestedController extends Controller
{
    use PaginatesApi;

    public function settingsGet(): JsonResponse
    {
        $settings = HrmPayrollSetting::query()->pluck('value', 'key');

        return response()->json(['data' => $settings]);
    }

    public function settingsSave(Request $request): JsonResponse
    {
        $data = $request->validate(['settings' => 'required|array']);
        foreach ($data['settings'] as $key => $value) {
            HrmPayrollSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        return response()->json(['message' => 'Settings saved']);
    }

    public function componentsIndex(Request $request): JsonResponse
    {
        return $this->paginatedResponse(HrmPayrollComponent::query()->orderBy('name')->paginate($this->perPage($request)));
    }

    public function componentsStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'type' => 'required|in:earning,deduction',
            'calculation' => 'nullable|string|max:30',
            'default_amount' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);
        $component = HrmPayrollComponent::create($data);

        return response()->json(['data' => $component, 'message' => 'Component saved'], 201);
    }

    public function employeeSalariesGet(Request $request): JsonResponse
    {
        $query = HrmEmployeeSalary::query()->with('employee')->orderByDesc('effective_from');
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        return $this->paginatedResponse($query->paginate($this->perPage($request)));
    }

    public function employeeSalariesSave(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:hrm_employees,id',
            'base_salary' => 'required|numeric|min:0',
            'components' => 'nullable|array',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date',
        ]);
        $salary = HrmEmployeeSalary::create($data);
        HrmEmployee::query()->whereKey($data['employee_id'])->update(['base_salary' => $data['base_salary']]);

        return response()->json(['data' => $salary->load('employee'), 'message' => 'Salary saved'], 201);
    }

    public function runsIndex(Request $request): JsonResponse
    {
        return $this->paginatedResponse(HrmPayrollRun::query()->orderByDesc('year')->orderByDesc('month')->paginate($this->perPage($request)));
    }

    public function runStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);
        $data['created_by'] = $request->user()->id;
        $run = HrmPayrollRun::create($data);

        return response()->json(['data' => $run, 'message' => 'Run created'], 201);
    }

    public function runGet(HrmPayrollRun $run): JsonResponse
    {
        $run->load(['items.employee']);

        return response()->json(['data' => $run]);
    }

    public function runCalculate(HrmPayrollRun $run): JsonResponse
    {
        $employees = HrmEmployee::query()->where('status', 'active')->get();
        $total = 0;
        foreach ($employees as $employee) {
            $gross = (float) ($employee->base_salary ?? 0);
            $deductions = 0;
            $net = $gross - $deductions;
            HrmPayrollItem::query()->updateOrCreate(
                ['payroll_run_id' => $run->id, 'employee_id' => $employee->id],
                ['gross' => $gross, 'deductions' => $deductions, 'net' => $net]
            );
            $total += $net;
        }
        $run->update(['status' => 'calculated', 'total_amount' => $total]);

        return response()->json(['data' => $run->fresh('items.employee'), 'message' => 'Calculated']);
    }

    public function runApprove(HrmPayrollRun $run): JsonResponse
    {
        $run->update(['status' => 'approved']);

        return response()->json(['data' => $run, 'message' => 'Approved']);
    }

    public function payslipsList(HrmPayrollRun $run): JsonResponse
    {
        $items = $run->items()->with('employee')->get();

        return response()->json(['data' => $items]);
    }
}

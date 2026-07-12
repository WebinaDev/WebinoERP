<?php

namespace Modules\Hrm\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Hrm\Entities\HrmPayrollItem;
use Modules\Hrm\Entities\HrmPayrollRun;

class PayrollController extends Controller
{
    use PaginatesApi;

    public function index(Request $request): JsonResponse
    {
        $query = HrmPayrollRun::query()->orderByDesc('year')->orderByDesc('month');

        return $this->paginatedResponse($query->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'status' => 'nullable|string|max:20',
        ]);

        $data['created_by'] = $request->user()->id;
        $run = HrmPayrollRun::create($data);

        return response()->json(['data' => $run, 'message' => 'Payroll run created'], 201);
    }

    public function show(HrmPayrollRun $payroll): JsonResponse
    {
        $payroll->load(['items.employee']);

        return response()->json(['data' => $payroll]);
    }

    public function update(Request $request, HrmPayrollRun $payroll): JsonResponse
    {
        $data = $request->validate([
            'title' => 'sometimes|string|max:200',
            'status' => 'sometimes|string|max:20',
            'total_amount' => 'sometimes|numeric|min:0',
        ]);

        $payroll->update($data);

        return response()->json(['data' => $payroll->fresh(), 'message' => 'Payroll run updated']);
    }

    public function destroy(HrmPayrollRun $payroll): JsonResponse
    {
        $payroll->delete();

        return response()->noContent();
    }

    public function storeItem(Request $request, HrmPayrollRun $payroll): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:hrm_employees,id',
            'gross' => 'required|numeric|min:0',
            'deductions' => 'nullable|numeric|min:0',
            'net' => 'required|numeric|min:0',
        ]);

        $item = $payroll->items()->create($data);
        $payroll->update(['total_amount' => $payroll->items()->sum('net')]);

        return response()->json(['data' => $item->load('employee'), 'message' => 'Payroll item added'], 201);
    }

    public function destroyItem(HrmPayrollRun $payroll, HrmPayrollItem $item): JsonResponse
    {
        abort_unless($item->payroll_run_id === $payroll->id, 404);
        $item->delete();
        $payroll->update(['total_amount' => $payroll->items()->sum('net')]);

        return response()->noContent();
    }
}

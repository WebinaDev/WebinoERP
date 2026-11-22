<?php

namespace Modules\Hrm\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Hrm\Services\HrmManagementReportService;

class HrmManagementReportController extends Controller
{
    public function __construct(
        private readonly HrmManagementReportService $reportService
    ) {}

    /**
     * 1. گزارش لیست حقوق و دستمزد
     */
    public function payrollList(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'year' => 'nullable|integer',
            'month' => 'nullable|integer|min:1|max:12',
            'department_id' => 'nullable|integer',
            'subsidiary_company_id' => 'nullable|integer',
            'contract_type' => 'nullable|string',
            'engagement_type' => 'nullable|string',
        ]);

        $report = $this->reportService->getPayrollListReport($filters);

        return response()->json(['data' => $report]);
    }

    /**
     * 2. گزارش بیمه و مالیات
     */
    public function insuranceAndTax(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'year' => 'nullable|integer',
            'month' => 'nullable|integer|min:1|max:12',
            'department_id' => 'nullable|integer',
            'subsidiary_company_id' => 'nullable|integer',
            'contract_type' => 'nullable|string',
        ]);

        $report = $this->reportService->getInsuranceAndTaxReport($filters);

        return response()->json(['data' => $report]);
    }

    /**
     * 3. گزارش کارکرد و اضافه‌کاری
     */
    public function attendanceAndOvertime(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'year' => 'nullable|integer',
            'month' => 'nullable|integer|min:1|max:12',
            'department_id' => 'nullable|integer',
            'subsidiary_company_id' => 'nullable|integer',
            'contract_type' => 'nullable|string',
        ]);

        $report = $this->reportService->getAttendanceAndOvertimeReport($filters);

        return response()->json(['data' => $report]);
    }

    /**
     * 4. گزارش احکام و تغییرات پرسنلی
     */
    public function decreesAndPersonnelChanges(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'year' => 'nullable|integer',
            'month' => 'nullable|integer|min:1|max:12',
            'department_id' => 'nullable|integer',
            'subsidiary_company_id' => 'nullable|integer',
            'contract_type' => 'nullable|string',
        ]);

        $report = $this->reportService->getDecreesAndPersonnelChangesReport($filters);

        return response()->json(['data' => $report]);
    }

    /**
     * 5. گزارش وام و کسورات
     */
    public function loansAndDeductions(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'department_id' => 'nullable|integer',
            'subsidiary_company_id' => 'nullable|integer',
            'contract_type' => 'nullable|string',
        ]);

        $report = $this->reportService->getLoansAndDeductionsReport($filters);

        return response()->json(['data' => $report]);
    }

    /**
     * 6. گزارش‌های کنترلی پیش از تأیید نهایی دوره حقوق
     */
    public function preApprovalControlAudit(Request $request, int $runId): JsonResponse
    {
        $run = \Modules\Hrm\Entities\HrmPayrollRun::query()->findOrFail($runId);
        $thresholds = $request->validate([
            'overtime_hours_threshold' => 'nullable|numeric|min:0',
            'shift_allowance_threshold' => 'nullable|numeric|min:0',
        ]);

        $report = $this->reportService->getPayrollControlAuditReports($run, $thresholds);

        return response()->json(['data' => $report]);
    }
}

<?php

namespace Modules\Hrm\Services;

use Modules\Hrm\Entities\HrmPayrollItem;
use Modules\Hrm\Entities\HrmPayrollRun;
use Modules\Hrm\Support\HrmNotifier;

class HrmPayrollEndToEndWorkflowService
{
    public function __construct(
        private readonly IranianPayrollCalculator $calculator,
        private readonly HrmLegalDisketteService $disketteService,
        private readonly HrmStandardBankFormatService $bankService,
        private readonly HrmPayrollAccountingIntegrationService $accountingBridge
    ) {}

    /**
     * Run calculations directly via service layer (pure business logic, no controller calls).
     */
    public function calculateRun(HrmPayrollRun $run): void
    {
        $employees = \Modules\Hrm\Entities\HrmEmployee::query()->where('is_active', true)->get();

        foreach ($employees as $employee) {
            $computed = $this->calculator->forEmployee($employee, (int) $run->year, (int) $run->month, $run->id);

            HrmPayrollItem::query()->updateOrCreate(
                [
                    'payroll_run_id' => $run->id,
                    'employee_id' => $employee->id,
                ],
                [
                    'gross' => $computed['gross'],
                    'deductions' => $computed['deductions'],
                    'net' => $computed['net'],
                    'employee_insurance' => $computed['breakdown']['employee_insurance'] ?? 0,
                    'employer_insurance' => $computed['breakdown']['employer_insurance'] ?? 0,
                    'tax' => $computed['breakdown']['tax'] ?? 0,
                    'insurable_wage' => $computed['breakdown']['insurable'] ?? $computed['gross'],
                    'taxable_wage' => $computed['breakdown']['taxable'] ?? $computed['gross'],
                    'breakdown' => $computed['breakdown'],
                ]
            );
        }

        $run->update([
            'workflow_state' => 'review',
            'status' => 'draft',
        ]);
    }

    /**
     * Complete workflow execution:
     * 1. Calculate / verify items directly in service
     * 2. Post segregated journal vouchers to Accounting
     * 3. Generate SSO & Tax diskettes
     * 4. Generate bank transfer file
     * 5. Transition workflow: review -> approved -> locked
     */
    public function executeFullPayrollCycle(HrmPayrollRun $run, ?int $userId = null): array
    {
        // 1. Direct Service Calculation
        $this->calculateRun($run);

        // 2. Issue Accounting Vouchers
        $voucherResult = $this->accountingBridge->issuePayrollVouchers($run, $userId);

        // 3. Generate Legal Diskettes
        $ssoDiskette = $this->disketteService->generateSsoDiskette($run);
        $taxDiskette = $this->disketteService->generateTaxDiskette($run);

        // 4. Generate Bank Export
        $bankFile = $this->bankService->generateBankExport($run, 'paya_standard');

        // 5. Transition to locked/approved
        $run->update([
            'status' => 'approved',
            'workflow_state' => 'locked',
            'approved_at' => now(),
            'locked_at' => now(),
            'locked_by' => $userId,
        ]);

        return [
            'status' => 'completed',
            'payroll_run_id' => $run->id,
            'workflow_state' => 'locked',
            'items_count' => $run->items()->count(),
            'total_gross' => (float) $run->items()->sum('gross'),
            'total_net' => (float) $run->items()->sum('net'),
            'accounting_voucher' => $voucherResult,
            'sso_records' => $ssoDiskette['record_count'],
            'tax_records' => $taxDiskette['record_count'],
            'bank_paya_records' => $bankFile['total_records'],
            'bank_checksum' => $bankFile['checksum'],
            'message' => 'چرخه کامل و فرآیند ممیزی، محاسبه و قفل دوره حقوق و دستمزد با موفقیت نهایی گردید.',
        ];
    }
}

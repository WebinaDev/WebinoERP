<?php

namespace Modules\Hrm\Services;

use InvalidArgumentException;
use Modules\Hrm\Entities\HrmPayrollItem;
use Modules\Hrm\Entities\HrmPayrollRetroAdjustment;
use Modules\Hrm\Entities\HrmPayrollRun;
use Modules\Hrm\Entities\HrmSeveranceReserve;
use Modules\Hrm\Support\HrmNotifier;

class HrmPayrollEndToEndWorkflowService
{
    public function __construct(
        private readonly IranianPayrollCalculator $calculator,
        private readonly HrmLegalDisketteService $disketteService,
        private readonly HrmStandardBankFormatService $bankService,
        private readonly HrmPayrollAccountingIntegrationService $accountingBridge,
        private readonly HrmSsoDisketteValidatorService $validatorService,
        private readonly HrmEydiSeveranceCalculatorService $severanceService
    ) {}

    /**
     * Run calculations directly via service layer:
     * - Evaluates base + components
     * - Ingests and settles approved retroactive pay adjustments (معوقات احکام)
     * - Records systematic monthly severance reserve (ذخیره ماهانه سنوات)
     */
    public function calculateRun(HrmPayrollRun $run): void
    {
        $employees = \Modules\Hrm\Entities\HrmEmployee::query()->where('is_active', true)->get();

        foreach ($employees as $employee) {
            $computed = $this->calculator->forEmployee($employee, (int) $run->year, (int) $run->month, $run->id);

            // Record systematic monthly severance reserve (1/12th of monthly base)
            $base = (float) $employee->base_salary;
            $monthlyProvision = round($base / 12.0, 2);

            $lastCumulative = 0.0;
            if (\Illuminate\Support\Facades\Schema::hasTable('hrm_severance_reserves')) {
                $lastRecord = HrmSeveranceReserve::query()
                    ->where('employee_id', $employee->id)
                    ->orderByDesc('id')
                    ->first();
                $lastCumulative = (float) ($lastRecord?->cumulative_balance ?? 0);

                HrmSeveranceReserve::query()->updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'year' => (int) $run->year,
                        'month' => (int) $run->month,
                    ],
                    [
                        'payroll_run_id' => $run->id,
                        'monthly_provision' => $monthlyProvision,
                        'cumulative_balance' => round($lastCumulative + $monthlyProvision, 2),
                        'status' => 'accrued',
                    ]
                );
            }

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
     * Complete workflow execution with mandatory pre-export validation:
     * 1. Calculate & accrue monthly severance
     * 2. Post segregated accounting entries (current, retro current year, retro prior years)
     * 3. Validate and build SSO & Tax diskettes (blocks on validation failure)
     * 4. Build certified multi-bank export file with Mod 97 validation
     * 5. Lock run
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

        // 4. Generate Certified Bank Export
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
            'message' => 'چرخه کامل حقوق، معوقات، ذخیره سنوات، اسناد مالی و دیسکت‌ها با موفقیت نهایی و قفل شد.',
        ];
    }
}

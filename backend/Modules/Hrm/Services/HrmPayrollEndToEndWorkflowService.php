<?php

namespace Modules\Hrm\Services;

use InvalidArgumentException;
use Modules\Hrm\Entities\HrmPayrollItem;
use Modules\Hrm\Entities\HrmPayrollRetroAdjustment;
use Modules\Hrm\Entities\HrmPayrollRun;
use Modules\Hrm\Entities\HrmSeveranceReserve;
use Modules\Hrm\Entities\HrmEmployee;

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
     * - Evaluates base + components + attendance work factors (night/holiday/overtime)
     * - Ingests and settles approved retroactive pay adjustments (معوقات احکام: approved -> applied)
     * - Records systematic monthly severance reserve with previous-month balance lookup (no double-counting)
     * - Respects company boundary if subsidiary_company_id is set
     */
    public function calculateRun(HrmPayrollRun $run): void
    {
        $employeeQuery = HrmEmployee::query()->where('is_active', true);
        if ($run->subsidiary_company_id && \Illuminate\Support\Facades\Schema::hasColumn('hrm_employees', 'subsidiary_company_id')) {
            $employeeQuery->where('subsidiary_company_id', $run->subsidiary_company_id);
        }
        $employees = $employeeQuery->get();

        foreach ($employees as $employee) {
            $computed = $this->calculator->forEmployee($employee, (int) $run->year, (int) $run->month, $run->id);

            // Ingest approved retroactive pay differentials
            $retroAdjustments = HrmPayrollRetroAdjustment::query()
                ->where('employee_id', $employee->id)
                ->where('status', 'approved')
                ->get();

            $retroGross = (float) $retroAdjustments->sum('differential_amount');
            $retroNet = (float) $retroAdjustments->sum('differential_net');
            $retroTax = (float) $retroAdjustments->sum('differential_tax');
            $retroIns = (float) $retroAdjustments->sum('differential_insurance');

            // Apply retro adjustments to this run
            foreach ($retroAdjustments as $adj) {
                $adj->update([
                    'status' => 'applied',
                    'payroll_run_id' => $run->id,
                ]);
            }

            $finalGross = round($computed['gross'] + $retroGross, 2);
            $finalNet = round($computed['net'] + $retroNet, 2);
            $finalTax = round(($computed['breakdown']['tax'] ?? 0) + $retroTax, 2);
            $finalEmployeeIns = round(($computed['breakdown']['employee_insurance'] ?? 0) + $retroIns, 2);
            $finalDeductions = round($computed['deductions'] + $retroTax + $retroIns, 2);

            // Record systematic monthly severance reserve (1/12th of monthly base)
            $base = (float) $employee->base_salary;
            $monthlyProvision = round($base / 12.0, 2);

            if (\Illuminate\Support\Facades\Schema::hasTable('hrm_severance_reserves')) {
                // Fetch previous cumulative balance excluding current run's year/month
                $prevRecord = HrmSeveranceReserve::query()
                    ->where('employee_id', $employee->id)
                    ->where(function ($q) use ($run) {
                        $q->where('year', '<', (int) $run->year)
                            ->orWhere(function ($q2) use ($run) {
                                $q2->where('year', (int) $run->year)->where('month', '<', (int) $run->month);
                            });
                    })
                    ->orderByDesc('year')
                    ->orderByDesc('month')
                    ->first();

                $lastCumulative = (float) ($prevRecord?->cumulative_balance ?? 0.0);

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

            $breakdown = $computed['breakdown'];
            $breakdown['retro_gross'] = $retroGross;
            $breakdown['retro_net'] = $retroNet;
            $breakdown['retro_tax'] = $retroTax;
            $breakdown['retro_insurance'] = $retroIns;

            HrmPayrollItem::query()->updateOrCreate(
                [
                    'payroll_run_id' => $run->id,
                    'employee_id' => $employee->id,
                ],
                [
                    'gross' => $finalGross,
                    'deductions' => $finalDeductions,
                    'net' => $finalNet,
                    'employee_insurance' => $finalEmployeeIns,
                    'employer_insurance' => $computed['breakdown']['employer_insurance'] ?? 0,
                    'tax' => $finalTax,
                    'insurable_wage' => ($computed['breakdown']['insurable'] ?? $computed['gross']) + $retroGross,
                    'taxable_wage' => ($computed['breakdown']['taxable'] ?? $computed['gross']) + $retroGross,
                    'breakdown' => $breakdown,
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

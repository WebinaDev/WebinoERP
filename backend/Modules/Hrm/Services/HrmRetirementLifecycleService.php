<?php

namespace Modules\Hrm\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmRetireeRecord;

class HrmRetirementLifecycleService
{
    public function __construct(
        private readonly HrmEydiSeveranceCalculatorService $settlementService
    ) {}

    /**
     * Formal retirement transition pipeline:
     * 1. Change status to 'retired' and deactivate from active payroll runs
     * 2. Execute end-of-employment settlement (severance buyback + unused leave + eydi)
     * 3. Create comprehensive retiree profile with survivors data and supplemental pension
     */
    public function retireEmployee(
        HrmEmployee $employee,
        ?Carbon $retirementDate = null,
        string $pensionFund = 'sso',
        float $supplementalPension = 0.0,
        array $survivors = []
    ): HrmRetireeRecord {
        return DB::transaction(function () use ($employee, $retirementDate, $pensionFund, $supplementalPension, $survivors) {
            $date = $retirementDate ?: now();

            // 1. Perform legal final settlement
            $settlement = $this->settlementService->executeEmployeeSettlement($employee, 'retirement');

            // 2. Deactivate employee from regular payroll runs
            $employee->update([
                'status' => 'retired',
                'is_active' => false,
            ]);

            // 3. Create or update Retiree Profile
            return HrmRetireeRecord::query()->updateOrCreate(
                ['employee_id' => $employee->id],
                [
                    'retirement_date' => $date->toDateString(),
                    'pension_fund_type' => $pensionFund,
                    'final_severance_amount' => $settlement['severance_amount'],
                    'unused_leave_buyback_amount' => $settlement['leave_buyback_amount'],
                    'pension_supplement_monthly' => $supplementalPension,
                    'status' => 'active',
                    'survivors_data' => $survivors,
                ]
            );
        });
    }
}

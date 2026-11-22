<?php

namespace Modules\Hrm\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Hrm\Entities\HrmBulkDecreeBatch;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmEmploymentDecree;
use Modules\Hrm\Entities\HrmOrgPosition;
use Modules\Hrm\Entities\HrmOrgStructureHistory;

class HrmWorkforceAdministrationService
{
    public function __construct(
        private readonly HrmSerialService $serialService
    ) {}

    /**
     * Assign employee to an organizational position with historical logging
     */
    public function assignEmployeeToPosition(
        HrmEmployee $employee,
        HrmOrgPosition $position,
        string $changeType = 'appointment',
        ?string $changeReason = null,
        ?Carbon $effectiveFrom = null
    ): HrmOrgStructureHistory {
        return DB::transaction(function () use ($employee, $position, $changeType, $changeReason, $effectiveFrom) {
            $effectiveDate = $effectiveFrom ?: now();

            // Close prior active position history
            HrmOrgStructureHistory::query()
                ->where('employee_id', $employee->id)
                ->whereNull('effective_to')
                ->update(['effective_to' => $effectiveDate->copy()->subDay()->toDateString()]);

            // Mark old position as vacant if occupied
            HrmOrgPosition::query()
                ->where('id', '!=', $position->id)
                ->where('status', 'occupied')
                ->whereHas('histories', fn ($q) => $q->where('employee_id', $employee->id)->whereNull('effective_to'))
                ->update(['status' => 'vacant']);

            $position->update(['status' => 'occupied']);
            $employee->update([
                'department_id' => $position->department_id,
                'position' => $position->title,
                'rank_level' => $position->job_grade,
            ]);

            $decreeNo = $this->serialService->next('decree');

            return HrmOrgStructureHistory::query()->create([
                'position_id' => $position->id,
                'employee_id' => $employee->id,
                'effective_from' => $effectiveDate->toDateString(),
                'change_type' => $changeType,
                'decree_no' => $decreeNo,
                'change_reason' => $changeReason ?: "انتصاب به پست مصوب {$position->title}",
            ]);
        });
    }

    /**
     * Process bulk employment decree adjustments (e.g. annual statutory increase)
     *
     * @return array{batch_id: int, processed_count: int, status: string}
     */
    public function executeBulkDecreeBatch(
        string $title,
        string $decreeType,
        Carbon $effectiveFrom,
        float $percentageIncrease = 0.0,
        float $fixedAmountIncrease = 0.0,
        ?int $userId = null
    ): array {
        return DB::transaction(function () use ($title, $decreeType, $effectiveFrom, $percentageIncrease, $fixedAmountIncrease, $userId) {
            $batchNo = 'BATCH-' . now()->format('Ymd') . '-' . rand(1000, 9999);

            $batch = HrmBulkDecreeBatch::query()->create([
                'batch_no' => $batchNo,
                'title' => $title,
                'decree_type' => $decreeType,
                'effective_from' => $effectiveFrom->toDateString(),
                'percentage_increase' => $percentageIncrease,
                'fixed_amount_increase' => $fixedAmountIncrease,
                'status' => 'applied',
                'created_by' => $userId,
            ]);

            $employees = HrmEmployee::query()->where('is_active', true)->get();
            $count = 0;

            foreach ($employees as $employee) {
                $base = (float) $employee->base_salary;
                $increaseByPercent = round($base * ($percentageIncrease / 100.0), 2);
                $newBase = round($base + $increaseByPercent + $fixedAmountIncrease, 2);
                $newDaily = round($newBase / 30, 2);

                $decreeNo = $this->serialService->next('decree');

                HrmEmploymentDecree::query()->create([
                    'employee_id' => $employee->id,
                    'decree_no' => $decreeNo,
                    'decree_type' => $title,
                    'effective_from' => $effectiveFrom->toDateString(),
                    'base_salary' => $newBase,
                    'daily_wage' => $newDaily,
                    'status' => 'approved',
                    'notes' => "صدور حکم گروهی اداری: {$title} (بچ شماره {$batchNo})",
                ]);

                $employee->update([
                    'base_salary' => $newBase,
                    'daily_rate' => $newDaily,
                ]);

                $count++;
            }

            $batch->update(['total_employees' => $count]);

            return [
                'batch_id' => $batch->id,
                'batch_no' => $batch->batch_no,
                'processed_count' => $count,
                'status' => 'applied',
            ];
        });
    }
}

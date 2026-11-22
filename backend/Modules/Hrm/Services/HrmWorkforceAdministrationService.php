<?php

namespace Modules\Hrm\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Hrm\Entities\HrmBulkDecreeBatch;
use Modules\Hrm\Entities\HrmDepartment;
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
     * Record structural organizational change history (Department transfer, Promotion, Demotion, Structural Reorg).
     */
    public function recordOrgStructureHistory(
        HrmEmployee $employee,
        ?HrmOrgPosition $newPosition,
        string $changeType,
        string $changeReason,
        Carbon $effectiveFrom,
        ?Carbon $effectiveTo = null
    ): HrmOrgStructureHistory {
        return DB::transaction(function () use ($employee, $newPosition, $changeType, $changeReason, $effectiveFrom, $effectiveTo) {
            // Close prior active position history if open
            HrmOrgStructureHistory::query()
                ->where('employee_id', $employee->id)
                ->whereNull('effective_to')
                ->update(['effective_to' => $effectiveFrom->copy()->subDay()->toDateString()]);

            $decreeNo = $this->serialService->next('decree');

            if ($newPosition) {
                $employee->update([
                    'department_id' => $newPosition->department_id,
                    'position' => $newPosition->title,
                    'rank_level' => $newPosition->job_grade,
                ]);
                $newPosition->update(['status' => 'occupied']);
            }

            return HrmOrgStructureHistory::query()->create([
                'position_id' => $newPosition?->id,
                'employee_id' => $employee->id,
                'effective_from' => $effectiveFrom->toDateString(),
                'effective_to' => $effectiveTo?->toDateString(),
                'change_type' => $changeType,
                'decree_no' => $decreeNo,
                'change_reason' => $changeReason,
            ]);
        });
    }

    /**
     * Process bulk employment decree adjustments (صدور حکم گروهی اداری e.g. annual statutory increase, inflation revision):
     * - Creates batch record in hrm_bulk_decree_batches
     * - Generates approved decree records in hrm_employment_decrees for targeted employees
     * - Sets effective_from (e.g. next month or beginning of fiscal year)
     * - Records structural/compensation history
     * - Immediately updates active base salary if effective date has arrived or applies to future payroll runs automatically.
     *
     * @param array<int>|null $targetEmployeeIds
     * @return array{batch_id: int, batch_no: string, processed_count: int, status: string}
     */
    public function executeBulkDecreeBatch(
        string $title,
        string $decreeType,
        Carbon $effectiveFrom,
        float $percentageIncrease = 0.0,
        float $fixedAmountIncrease = 0.0,
        ?int $userId = null,
        ?array $targetEmployeeIds = null
    ): array {
        return DB::transaction(function () use ($title, $decreeType, $effectiveFrom, $percentageIncrease, $fixedAmountIncrease, $userId, $targetEmployeeIds) {
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

            $query = HrmEmployee::query()->where('is_active', true);
            if (! empty($targetEmployeeIds)) {
                $query->whereIn('id', $targetEmployeeIds);
            }
            $employees = $query->get();
            $count = 0;

            $isEffectiveNowOrPast = $effectiveFrom->isPast() || $effectiveFrom->isToday();

            foreach ($employees as $employee) {
                // Determine current active baseline
                $currentBase = (float) $employee->base_salary;
                $increaseByPercent = round($currentBase * ($percentageIncrease / 100.0), 2);
                $newBase = round($currentBase + $increaseByPercent + $fixedAmountIncrease, 2);
                $newDaily = round($newBase / 30.0, 2);

                $decreeNo = $this->serialService->next('decree');

                // 1. Issue Approved Employment Decree
                HrmEmploymentDecree::query()->create([
                    'employee_id' => $employee->id,
                    'user_id' => $userId,
                    'decree_no' => $decreeNo,
                    'decree_type' => $decreeType,
                    'status' => 'approved',
                    'effective_from' => $effectiveFrom->toDateString(),
                    'job_title' => $employee->position ?: 'کارشناس',
                    'department' => $employee->department?->name ?? 'عمومی',
                    'contract_type' => $employee->contract_type ?? 'permanent',
                    'engagement_type' => $employee->engagement_type ?? 'full_time',
                    'pay_basis' => $employee->pay_basis ?? 'monthly',
                    'base_salary' => $newBase,
                    'daily_wage' => $newDaily,
                    'insurance_applicable' => true,
                    'tax_applicable' => ! $employee->tax_exempt,
                    'notes' => "صدور حکم گروهی کارگزینی: {$title} (بچ شماره {$batchNo})",
                ]);

                // 2. Track in OrgStructureHistory
                HrmOrgStructureHistory::query()->create([
                    'position_id' => null,
                    'employee_id' => $employee->id,
                    'effective_from' => $effectiveFrom->toDateString(),
                    'change_type' => 'bulk_salary_adjustment',
                    'decree_no' => $decreeNo,
                    'change_reason' => "اصلاح گروهی احکام کارگزینی: {$title} (+{$percentageIncrease}% + " . number_format($fixedAmountIncrease) . " ریال)",
                ]);

                // 3. Update employee master record if already effective today or in past
                if ($isEffectiveNowOrPast) {
                    $employee->update([
                        'base_salary' => $newBase,
                        'daily_rate' => $newDaily,
                    ]);
                }

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

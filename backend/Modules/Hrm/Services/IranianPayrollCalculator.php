<?php

namespace Modules\Hrm\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Modules\Hrm\Entities\HrmAttendanceRecord;
use Modules\Hrm\Entities\HrmCompensationPackage;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmEmploymentDecree;
use Modules\Hrm\Entities\HrmLoan;
use Modules\Hrm\Entities\HrmLoanInstallment;
use Modules\Hrm\Entities\HrmMissionOrder;
use Modules\Hrm\Entities\HrmPayrollItem;
use Modules\Hrm\Entities\HrmPayrollRetroAdjustment;
use Modules\Hrm\Entities\HrmPayrollRun;
use Modules\Hrm\Entities\HrmPayrollSetting;
use Modules\Hrm\Entities\HrmSalaryStructure;
use Modules\Hrm\Entities\HrmTimesheet;

class IranianPayrollCalculator
{
    public function settings(): array
    {
        $defaults = [
            'housing_allowance' => 14000000,
            'bon_allowance' => 18000000,
            'child_allowance_per_child' => 7166184,
            'marriage_allowance' => 5000000,
            'monthly_hours' => 220,
            'overtime_multiplier' => 1.4,
            'night_shift_multiplier' => 1.35,
            'holiday_shift_multiplier' => 1.40,
            'mission_daily_allowance' => 1500000,
            'employee_insurance_percent' => 7,
            'employer_insurance_percent' => 20,
            'unemployment_insurance_percent' => 3,
            'insurance_ceiling' => 221550000,
            'eydi_min_wage_months' => 2,
            'eydi_cap_min_wage_months' => 3,
            'eydi_prorate' => true,
            'severance_months_per_year' => 1,
            'severance_prorate' => true,
            'insurance_deductible_fraction' => 2 / 7,
            'tax_brackets' => [
                ['limit' => 400000000, 'rate' => 0.0],
                ['limit' => 700000000, 'rate' => 0.10],
                ['limit' => 1000000000, 'rate' => 0.15],
                ['limit' => 1400000000, 'rate' => 0.20],
                ['limit' => null, 'rate' => 0.30],
            ],
        ];

        if (Schema::hasTable('hrm_payroll_settings')) {
            $rows = HrmPayrollSetting::all()->pluck('value', 'key')->toArray();
            foreach ($rows as $k => $v) {
                if (isset($defaults[$k])) {
                    $defaults[$k] = is_numeric($v) ? (float) $v : $v;
                }
            }
        }

        return $defaults;
    }

    public function forEmployee(HrmEmployee $employee, int $year, int $month, ?int $runId = null): array
    {
        $settings = $this->settings();
        $engagement = $employee->engagement_type ?? 'full_time';

        // 1. Resolve Effective Base Salary:
        // Automatically check for approved employment decrees (احکام کارگزینی) effective for this month
        $base = (float) $employee->base_salary;
        $targetMonthEnd = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

        if (Schema::hasTable('hrm_employment_decrees')) {
            $effectiveDecree = HrmEmploymentDecree::query()
                ->where('employee_id', $employee->id)
                ->where('status', 'approved')
                ->where('effective_from', '<=', $targetMonthEnd)
                ->where(function ($q) use ($targetMonthEnd) {
                    $q->whereNull('effective_to')->orWhere('effective_to', '>=', $targetMonthEnd);
                })
                ->latest('effective_from')
                ->first();

            if ($effectiveDecree && (float) $effectiveDecree->base_salary > 0) {
                $base = (float) $effectiveDecree->base_salary;
            }
        }

        $lines = [];

        // Engagement rules: statutory benefits
        $housing = (float) ($settings['housing_allowance'] ?? 0);
        $bon = (float) ($settings['bon_allowance'] ?? 0);

        if (in_array($engagement, ['freelance', 'project'], true)) {
            $housing = 0.0;
            $bon = 0.0;
        } elseif ($engagement === 'part_time' || $engagement === 'remote') {
            $ratio = $this->prorationRatio($employee->id, $year, $month, (float) ($settings['monthly_hours'] ?? 220));
            $housing = round($housing * $ratio, 2);
            $bon = round($bon * $ratio, 2);
        }

        if ($housing > 0) {
            $lines[] = ['title' => 'حق مسکن', 'amount' => $housing, 'is_insurable' => true, 'is_taxable' => true];
        }
        if ($bon > 0) {
            $lines[] = ['title' => 'بن و خواربار', 'amount' => $bon, 'is_insurable' => true, 'is_taxable' => true];
        }

        // Child allowance (اولاد)
        $children = (int) ($employee->profile?->children_count ?? 0);
        if ($children > 0 && ! in_array($engagement, ['freelance', 'project'], true)) {
            $childRate = (float) ($settings['child_allowance_per_child'] ?? 0);
            $childTotal = round($childRate * $children, 2);
            $lines[] = ['title' => "حق اولاد ({$children} فرزند)", 'amount' => $childTotal, 'is_insurable' => false, 'is_taxable' => false];
        }

        // Marriage allowance (تأهل)
        $marital = $employee->marital_status ?? 'single';
        if ($marital === 'married' && ! in_array($engagement, ['freelance', 'project'], true)) {
            $marriage = (float) ($settings['marriage_allowance'] ?? 0);
            if ($marriage > 0) {
                $lines[] = ['title' => 'حق تأهل', 'amount' => $marriage, 'is_insurable' => true, 'is_taxable' => true];
            }
        }

        // Active Compensation Package or Custom Salary Structure Components
        $insuranceOn = ! in_array($engagement, ['freelance', 'project'], true);
        $taxOn = $employee->tax_exempt ? false : true;

        if (Schema::hasTable('hrm_compensation_packages')) {
            $package = HrmCompensationPackage::query()
                ->where('employee_id', $employee->id)
                ->where('is_active', true)
                ->where('effective_from', '<=', Carbon::create($year, $month, 1)->endOfMonth()->toDateString())
                ->latest('effective_from')
                ->first();

            if ($package) {
                $structure = HrmSalaryStructure::query()->where('package_id', $package->id)->first();
                if ($structure && is_array($structure->components)) {
                    foreach ($structure->components as $c) {
                        $amt = (float) ($c['amount'] ?? 0);
                        if ($amt > 0) {
                            $lines[] = [
                                'title' => $c['title'] ?? 'مزایای مستمر',
                                'amount' => $amt,
                                'is_insurable' => (bool) ($c['is_insurable'] ?? true),
                                'is_taxable' => (bool) ($c['is_taxable'] ?? true),
                            ];
                        }
                    }
                }
            }
        }

        // Dynamic lookup from hrm_salary_components (if configured)
        if (Schema::hasTable('hrm_salary_components') && Schema::hasTable('hrm_employee_salary_components')) {
            $assignedComponents = $employee->salaryComponents()
                ->where('is_active', true)
                ->get();

            foreach ($assignedComponents as $component) {
                $amount = (float) $component->pivot->amount;
                $lines[] = [
                    'title' => $component->name,
                    'amount' => $amount,
                    'is_insurable' => (bool) $component->is_insurable,
                    'is_taxable' => (bool) ($component->is_taxable ?? true),
                ];
            }
        }

        // Compute insurable & taxable base from lines
        $insurable = $insuranceOn ? $base : 0.0;
        $taxableEarnings = $taxOn ? $base : 0.0;

        foreach ($lines as $ln) {
            $amount = (float) $ln['amount'];
            if ($insuranceOn && ($ln['is_insurable'] ?? true)) {
                $insurable += $amount;
            }
            if ($taxOn && ($ln['is_taxable'] ?? true)) {
                $taxableEarnings += $amount;
            }
        }

        // Direct integration with Attendance Work Rule Engine & approved hours
        $workRuleEngine = app(HrmAttendanceWorkRuleEngine::class);
        $attendanceFactors = $workRuleEngine->evaluateMonthlyAttendanceFactors($employee, $year, $month);

        $monthlyHours = max(1, (float) ($settings['monthly_hours'] ?? 220));
        $hourly = $base > 0 ? $base / $monthlyHours : 0;

        // Overtime: max of timesheets approved overtime vs physical attendance records
        $overtimeHours = max($this->approvedHours($employee->id, 'overtime', $year, $month), (float) ($attendanceFactors['overtime_hours'] ?? 0));
        $overtimeMultiplier = (float) ($settings['overtime_multiplier'] ?? 1.4);
        $overtime = round($overtimeHours * $hourly * $overtimeMultiplier, 2);

        // Night work allowance (فوق‌العاده شب‌کاری ماده ۵۸ - ۳۵٪)
        $nightHours = max($this->approvedHours($employee->id, 'night', $year, $month), (float) ($attendanceFactors['night_hours'] ?? 0));
        $nightMultiplier = (float) ($settings['night_shift_multiplier'] ?? 1.35) - 1.0; // differential extra (35%)
        $nightAllowance = round($nightHours * $hourly * $nightMultiplier, 2);

        // Holiday work allowance (فوق‌العاده تعطیل‌کاری ماده ۶۲ - ۴۰٪)
        $holidayHours = max($this->approvedHours($employee->id, 'holiday', $year, $month), (float) ($attendanceFactors['holiday_hours'] ?? 0));
        $holidayMultiplier = (float) ($settings['holiday_shift_multiplier'] ?? 1.40) - 1.0; // differential extra (40%)
        $holidayAllowance = round($holidayHours * $hourly * $holidayMultiplier, 2);

        // Shift rotation allowance (فوق‌العاده نوبت‌کاری مواد ۵۵ و ۵۶ بر اساس درصد پایه)
        $shiftPercent = (float) ($attendanceFactors['shift_allowance_percent'] ?? 0);
        $shiftAllowance = round($base * ($shiftPercent / 100.0), 2);

        // Mission Allowance (ماده ۴۶ قانون کار)
        $missionDays = max($this->approvedHours($employee->id, 'mission', $year, $month, 'days'), (float) ($attendanceFactors['mission_days'] ?? 0));
        $missionAmountFromOrders = (float) ($attendanceFactors['mission_pay'] ?? 0);
        $missionAmountFromCalc = $this->approvedHours($employee->id, 'mission', $year, $month, 'amount');
        $missionAmount = max($missionAmountFromOrders, $missionAmountFromCalc);

        $dailyAllowanceConfig = (float) ($settings['mission_daily_allowance'] ?? 0);
        $statutoryMissionAllowance = $missionDays > 0 ? max($missionAmount, round($missionDays * $dailyAllowanceConfig, 2)) : $missionAmount;
        $mission = round($statutoryMissionAllowance, 2);

        $shiftTotal = round($nightAllowance + $holidayAllowance + $shiftAllowance, 2);
        $earnings = round(array_sum(array_column($lines, 'amount')) + $overtime + $shiftTotal + $mission, 2);
        $gross = round($base + $earnings, 2);

        if ($insuranceOn) {
            $insurable += $overtime + $shiftTotal + $mission;
        }
        if ($taxOn) {
            $taxableEarnings += $overtime + $shiftTotal + $mission;
        }

        $ceiling = (float) ($settings['insurance_ceiling'] ?? 0);
        $insurableBase = $ceiling > 0 ? min($insurable, $ceiling) : $insurable;
        $employeeIns = $insuranceOn ? round($insurableBase * ((float) ($settings['employee_insurance_percent'] ?? 7)) / 100, 2) : 0.0;
        $employerIns = $insuranceOn ? round($insurableBase * ((float) ($settings['employer_insurance_percent'] ?? 20)) / 100, 2) : 0.0;
        $unemployment = $insuranceOn ? round($insurableBase * ((float) ($settings['unemployment_insurance_percent'] ?? 3)) / 100, 2) : 0.0;

        $fraction = (float) ($settings['insurance_deductible_fraction'] ?? (2 / 7));
        $taxable = $taxOn ? max(0, $taxableEarnings - ($employeeIns * $fraction)) : 0.0;
        $tax = $taxOn ? $this->calculateTax($taxable, $settings['tax_brackets'] ?? []) : 0.0;

        // Apply real loan deductions
        $loanDeduction = 0.0;
        $advanceDeduction = 0.0;
        if ($runId) {
            [$loanDeduction, $advanceDeduction] = $this->applyLoanInstallments($employee->id, $runId);
        } else {
            // Preview loan installments without saving
            $activeLoans = HrmLoan::query()
                ->where('employee_id', $employee->id)
                ->where('status', 'active')
                ->where('remaining_balance', '>', 0)
                ->get();

            foreach ($activeLoans as $l) {
                $ded = min((float) $l->remaining_balance, (float) $l->installment_amount);
                if ($l->type === 'advance') {
                    $advanceDeduction += $ded;
                } else {
                    $loanDeduction += $ded;
                }
            }
        }

        $otherDeductions = round($loanDeduction + $advanceDeduction, 2);
        $deductions = round($employeeIns + $tax + $otherDeductions, 2);
        $net = round($gross - $deductions, 2);

        return [
            'gross' => $gross,
            'deductions' => $deductions,
            'net' => $net,
            'breakdown' => [
                'base' => $base,
                'lines' => $lines,
                'overtime' => $overtime,
                'night_allowance' => $nightAllowance,
                'holiday_allowance' => $holidayAllowance,
                'shift_allowance' => $shiftAllowance,
                'shift_pattern' => (string) ($attendanceFactors['shift_pattern'] ?? 'standard'),
                'mission' => $mission,
                'gross' => $gross,
                'insurable' => round($insurableBase, 2),
                'employee_insurance' => $employeeIns,
                'employer_insurance' => $employerIns,
                'unemployment_insurance' => $unemployment,
                'taxable' => round($taxable, 2),
                'tax' => $tax,
                'loan_deduction' => $loanDeduction,
                'advance_deduction' => $advanceDeduction,
                'other_deductions' => round($otherDeductions, 2),
            ],
        ];
    }

    public function calculateTax(float $taxable, array $brackets): float
    {
        if ($taxable <= 0) {
            return 0.0;
        }

        $tax = 0.0;
        $prevLimit = 0.0;

        foreach ($brackets as $b) {
            $limit = $b['limit'] !== null ? (float) $b['limit'] : null;
            $rate = (float) $b['rate'];

            if ($limit === null) {
                if ($taxable > $prevLimit) {
                    $tax += ($taxable - $prevLimit) * $rate;
                }
                break;
            }

            if ($taxable > $limit) {
                $tax += ($limit - $prevLimit) * $rate;
                $prevLimit = $limit;
            } else {
                if ($taxable > $prevLimit) {
                    $tax += ($taxable - $prevLimit) * $rate;
                }
                break;
            }
        }

        return round($tax, 2);
    }

    public function prorationRatio(int $employeeId, int $year, int $month, float $monthlyHours = 220.0): float
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = Carbon::create($year, $month, 1)->endOfMonth();

        $workedHours = 0.0;

        if (Schema::hasTable('hrm_timesheets')) {
            $workedHours = (float) HrmTimesheet::query()
                ->where('employee_id', $employeeId)
                ->where('status', 'approved')
                ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
                ->sum('hours');
        }

        if ($workedHours <= 0 && Schema::hasTable('hrm_attendance_records')) {
            $records = HrmAttendanceRecord::query()
                ->where('employee_id', $employeeId)
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->get();

            foreach ($records as $r) {
                $workedHours += (float) ($r->worked_hours ?: 0);
            }
        }

        if ($workedHours <= 0) {
            return 1.0; // fallback full
        }

        return min(1.0, round($workedHours / max(1.0, $monthlyHours), 4));
    }

    /**
     * Real calculation of approved overtime, night, holiday, or mission hours.
     * Dynamic lookup supporting hour_type on timesheet, fallback to WorkRuleEngine, and settings['monthly_hours'].
     */
    public function approvedHours(int $employeeId, string $type, int $year, int $month, string $mode = 'hours'): float
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = Carbon::create($year, $month, 1)->endOfMonth();

        if ($type === 'mission') {
            if (! Schema::hasTable('hrm_mission_orders')) {
                return 0.0;
            }

            $orders = HrmMissionOrder::query()
                ->where('employee_id', $employeeId)
                ->whereIn('status', ['hr_approved', 'settled', 'manager_approved'])
                ->whereBetween('starts_at', [$start->toDateTimeString(), $end->toDateTimeString()])
                ->get();

            if ($mode === 'days') {
                $days = 0.0;
                foreach ($orders as $order) {
                    $s = Carbon::parse($order->starts_at);
                    $e = Carbon::parse($order->ends_at);
                    $days += max(1.0, round($e->diffInHours($s) / 24.0, 1));
                }
                return $days;
            }

            if ($mode === 'amount') {
                $sum = 0.0;
                foreach ($orders as $order) {
                    $amt = (float) $order->per_diem_rate;
                    if ($amt <= 0) {
                        $amt = (float) $order->total_allowance;
                    }
                    $sum += $amt;
                }
                return $sum;
            }

            return 0.0;
        }

        if (Schema::hasTable('hrm_timesheets')) {
            $hasHourType = Schema::hasColumn('hrm_timesheets', 'hour_type');

            if ($hasHourType) {
                $explicitHours = (float) HrmTimesheet::query()
                    ->where('employee_id', $employeeId)
                    ->where('status', 'approved')
                    ->where('hour_type', $type)
                    ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
                    ->sum('hours');

                if ($explicitHours > 0) {
                    return $explicitHours;
                }
            }

            // 1. Fallback for Overtime: if timesheets logged standard/null hours exceeding standard monthly hours
            if ($type === 'overtime') {
                $timesheetHours = (float) HrmTimesheet::query()
                    ->where('employee_id', $employeeId)
                    ->where('status', 'approved')
                    ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
                    ->sum('hours');

                $settings = $this->settings();
                $standardMonthlyHours = (float) ($settings['monthly_hours'] ?? 220);

                if ($timesheetHours > $standardMonthlyHours) {
                    return round($timesheetHours - $standardMonthlyHours, 2);
                }
            }

            // 2. Fallback for Night & Holiday: evaluate from physical work records or WorkRuleEngine
            if ($type === 'night' || $type === 'holiday') {
                $employee = HrmEmployee::query()->find($employeeId);
                if ($employee) {
                    $workRuleEngine = app(HrmAttendanceWorkRuleEngine::class);
                    $factors = $workRuleEngine->evaluateMonthlyAttendanceFactors($employee, $year, $month);
                    return $type === 'night'
                        ? (float) ($factors['night_hours'] ?? 0.0)
                        : (float) ($factors['holiday_hours'] ?? 0.0);
                }
            }
        }

        return 0.0;
    }

    /**
     * Real loan and salary advance installment deduction:
     * - Finds active loans with remaining_balance > 0
     * - Creates or updates real installment records in hrm_loan_installments
     * - Syncs loan remaining balance and increments installments_paid
     */
    public function applyLoanInstallments(int $employeeId, int $runId): array
    {
        if (! Schema::hasTable('hrm_loans') || ! Schema::hasTable('hrm_loan_installments')) {
            return [0.0, 0.0];
        }

        $activeLoans = HrmLoan::query()
            ->where('employee_id', $employeeId)
            ->where('status', 'active')
            ->where('remaining_balance', '>', 0)
            ->get();

        $loanTotal = 0.0;
        $advanceTotal = 0.0;

        foreach ($activeLoans as $loan) {
            $deduction = min((float) $loan->remaining_balance, (float) $loan->installment_amount);
            if ($deduction <= 0) {
                continue;
            }

            // Record installment
            HrmLoanInstallment::query()->updateOrCreate(
                [
                    'loan_id' => $loan->id,
                    'payroll_run_id' => $runId,
                ],
                [
                    'amount' => $deduction,
                    'paid_at' => now(),
                    'is_settled' => true,
                ]
            );

            // Sync balance and installments count
            $this->syncLoanBalance($loan->id, $deduction, $runId);

            if ($loan->type === 'advance') {
                $advanceTotal += $deduction;
            } else {
                $loanTotal += $deduction;
            }
        }

        return [round($loanTotal, 2), round($advanceTotal, 2)];
    }

    /**
     * Synchronize loan balance after installment deduction and mark settled if fully repaid.
     */
    public function syncLoanBalance(int $loanId, float $installmentAmount, ?int $runId = null): void
    {
        if (! Schema::hasTable('hrm_loans')) {
            return;
        }

        $loan = HrmLoan::query()->find($loanId);
        if (! $loan) {
            return;
        }

        $newBalance = max(0.0, round((float) $loan->remaining_balance - $installmentAmount, 2));
        $paidCount = (int) $loan->installments_paid + 1;

        $updates = [
            'remaining_balance' => $newBalance,
            'installments_paid' => $paidCount,
        ];

        if ($newBalance <= 0) {
            $updates['status'] = 'settled';
        }

        $loan->update($updates);
    }
}

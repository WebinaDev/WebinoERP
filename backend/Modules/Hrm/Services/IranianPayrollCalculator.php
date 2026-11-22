<?php

namespace Modules\Hrm\Services;

use Carbon\Carbon;
use Modules\Hrm\Entities\HrmDependent;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmLoan;
use Modules\Hrm\Entities\HrmLoanInstallment;
use Modules\Hrm\Entities\HrmPayrollComponent;
use Modules\Hrm\Entities\HrmPayrollItem;
use Modules\Hrm\Entities\HrmPayrollSetting;
use Modules\Hrm\Support\HrmCompensationRules;
use Modules\Hrm\Support\HrmTaxBrackets;
use Illuminate\Support\Facades\Schema;

class IranianPayrollCalculator
{
    /**
     * Default enterprise Iranian payroll parameters (1405 statutory rates).
     *
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'minimum_daily_wage' => 3165000,
            'housing_allowance' => 14000000,
            'bon_allowance' => 18000000,
            'child_allowance_per_child' => 9495000,
            'marital_allowance' => 5000000,
            'working_days_per_month' => 30,
            'monthly_hours' => 220,
            'overtime_multiplier' => 1.4,
            'night_shift_multiplier' => 1.35,
            'holiday_shift_multiplier' => 1.40,
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
                ['up_to' => 400000000, 'rate' => 0],
                ['up_to' => 800000000, 'rate' => 10],
                ['up_to' => 1000000000, 'rate' => 15],
                ['up_to' => 1200000000, 'rate' => 20],
                ['up_to' => 1400000000, 'rate' => 25],
                ['up_to' => null, 'rate' => 30],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $stored = [];
        if (Schema::hasTable('hrm_payroll_settings')) {
            foreach (HrmPayrollSetting::query()->get() as $row) {
                $stored[$row->key] = $row->value;
            }
        }

        return array_replace($this->defaults(), $stored);
    }

    public function resetRun(int $runId): void
    {
        if (! Schema::hasTable('hrm_loan_installments')) {
            return;
        }

        $loanIds = HrmLoanInstallment::query()->where('payroll_run_id', $runId)->pluck('loan_id');
        HrmLoanInstallment::query()->where('payroll_run_id', $runId)->delete();
        if ($loanIds->isEmpty() || ! Schema::hasTable('hrm_loans')) {
            return;
        }
        foreach (HrmLoan::query()->whereIn('id', $loanIds)->get() as $loan) {
            $this->syncLoanBalance($loan);
        }
    }

    /**
     * @return array{gross: float, deductions: float, net: float, breakdown: array<string, mixed>}
     */
    public function forEmployee(HrmEmployee $employee, int $year, int $month, int $runId): array
    {
        $settings = $this->settings();
        app(HrmCompensationRules::class)->assertEmployee($employee, $settings);
        $engagement = (string) ($employee->engagement_type ?: 'full_time');
        $basis = (string) ($employee->pay_basis ?: 'monthly');
        $workingDays = max(1, (float) ($settings['working_days_per_month'] ?? 30));
        $monthlyHoursSetting = max(1, (float) ($settings['monthly_hours'] ?? 220));
        $base = match ($basis) {
            'project_fee' => (float) ($employee->project_fee ?? 0),
            'daily' => (float) ($employee->daily_rate ?? 0) * $workingDays,
            'hourly' => (float) ($employee->hourly_rate ?? 0) * $monthlyHoursSetting,
            default => (float) ($employee->base_salary ?? 0),
        };
        $benefitEngagements = $settings['apply_benefits_engagements'] ?? ['full_time', 'part_time', 'remote'];
        $applyBenefits = is_array($benefitEngagements) && in_array($engagement, $benefitEngagements, true);
        $insuranceOn = $this->applicability($employee, 'insurance_applicable', $engagement, ['freelance', 'project']);
        $taxOn = $this->applicability($employee, 'tax_applicable', $engagement, []);
        $lines = [];
        $standard = [
            'bon' => 0.0, 'housing' => 0.0, 'child' => 0.0, 'seniority' => 0.0,
            'reward' => 0.0, 'eydi' => 0.0, 'bon_card' => 0.0, 'marital' => 0.0, 'severance' => 0.0,
        ];
        $insurable = $insuranceOn ? $base : 0.0;
        $taxableEarnings = $taxOn ? $base : 0.0;

        $insuredDependents = 0;
        if (Schema::hasTable('hrm_dependents')) {
            $q = HrmDependent::query()->where('employee_id', $employee->id);
            if (Schema::hasColumn('hrm_dependents', 'is_insured')) {
                $q->where('is_insured', true);
            }
            $insuredDependents = $q->count();
        }

        $salaryOverrides = $this->salaryComponentOverrides($employee);

        if (Schema::hasTable('hrm_payroll_components')) {
            $components = HrmPayrollComponent::query()->where('is_active', true)->orderBy('id')->get();
            foreach ($components as $component) {
                if ($component->type !== 'earning') {
                    continue;
                }
                if (! $applyBenefits) {
                    continue;
                }
                $override = $salaryOverrides[(string) $component->code] ?? null;
                if (is_array($override) && array_key_exists('enabled', $override) && $override['enabled'] === false) {
                    continue;
                }
                $overrideAmount = is_array($override) && array_key_exists('amount', $override) && $override['amount'] !== null && $override['amount'] !== ''
                    ? (float) $override['amount']
                    : null;
                $amount = $this->componentAmount($component, $base, $year, $month, $settings, $insuredDependents, $employee, $overrideAmount);
                if ($amount <= 0) {
                    continue;
                }
                $lines[] = [
                    'code' => $component->code,
                    'name' => $component->name,
                    'amount' => $amount,
                ];
                if (isset($standard[(string) $component->code])) {
                    $standard[(string) $component->code] = $amount;
                }
                if ($insuranceOn && $component->is_insurable) {
                    $insurable += $amount;
                }
                if ($taxOn && ($component->is_taxable ?? true)) {
                    $taxableEarnings += $amount;
                }
            }
        }

        // Direct integration with Attendance Work Rule Engine (ماده ۵۸، ۵۹ و ۶۲ قانون کار)
        $workRuleEngine = app(HrmAttendanceWorkRuleEngine::class);
        $attendanceFactors = $workRuleEngine->evaluateMonthlyAttendanceFactors($employee, $year, $month);

        $monthlyHours = max(1, (float) ($settings['monthly_hours'] ?? 220));
        $hourly = $base > 0 ? $base / $monthlyHours : 0;

        $overtimeHours = max($this->approvedHours($employee->id, 'overtime', $year, $month), (float) ($attendanceFactors['overtime_hours'] ?? 0));
        $overtimeMultiplier = (float) ($settings['overtime_multiplier'] ?? 1.4);
        $overtime = round($overtimeHours * $hourly * $overtimeMultiplier, 2);

        // Night work allowance (فوق‌العاده شب‌کاری ماده ۵۸ - ۳۵٪)
        $nightHours = (float) ($attendanceFactors['night_hours'] ?? 0);
        $nightMultiplier = (float) ($settings['night_shift_multiplier'] ?? 1.35) - 1.0; // differential extra (35%)
        $nightAllowance = round($nightHours * $hourly * $nightMultiplier, 2);

        // Holiday work allowance (فوق‌العاده تعطیل‌کاری ماده ۶۲ - ۴۰٪)
        $holidayHours = (float) ($attendanceFactors['holiday_hours'] ?? 0);
        $holidayMultiplier = (float) ($settings['holiday_shift_multiplier'] ?? 1.40) - 1.0; // differential extra (40%)
        $holidayAllowance = round($holidayHours * $hourly * $holidayMultiplier, 2);

        // Shift rotation allowance (فوق‌العاده نوبت‌کاری بر اساس درصد پایه)
        $shiftPercent = (float) ($attendanceFactors['shift_allowance_percent'] ?? 0);
        $shiftAllowance = round($base * ($shiftPercent / 100.0), 2);

        $missionDays = $this->approvedHours($employee->id, 'mission', $year, $month, 'days');
        $missionAmount = $this->approvedHours($employee->id, 'mission', $year, $month, 'amount');
        $mission = round($missionAmount + ($missionDays * (float) ($settings['mission_daily_allowance'] ?? 0)), 2);

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
        $brackets = is_array($settings['tax_brackets'] ?? null) ? $settings['tax_brackets'] : $this->defaults()['tax_brackets'];
        $tax = $taxOn ? $this->progressiveTax($taxable, $brackets) : 0.0;

        [$loanDeduction, $advanceDeduction] = $this->applyLoanInstallments($employee->id, $runId);

        $otherDeductions = 0.0;
        if (Schema::hasTable('hrm_payroll_components')) {
            foreach (HrmPayrollComponent::query()->where('is_active', true)->where('type', 'deduction')->get() as $component) {
                $override = $salaryOverrides[(string) $component->code] ?? null;
                if (is_array($override) && array_key_exists('enabled', $override) && $override['enabled'] === false) {
                    continue;
                }
                $otherDeductions += is_array($override) && array_key_exists('amount', $override) && $override['amount'] !== null && $override['amount'] !== ''
                    ? (float) $override['amount']
                    : (float) $component->default_amount;
            }
        }

        $deductions = round($employeeIns + $tax + $loanDeduction + $advanceDeduction + $otherDeductions, 2);
        $net = round($gross - $deductions, 2);

        return [
            'gross' => $gross,
            'deductions' => $deductions,
            'net' => $net,
            'breakdown' => [
                'base' => $base,
                'engagement_type' => $engagement,
                'pay_basis' => $basis,
                'insurance_applicable' => $insuranceOn,
                'tax_applicable' => $taxOn,
                'lines' => $standard,
                'earnings' => $lines,
                'earnings_total' => $earnings,
                'overtime_hours' => $overtimeHours,
                'overtime' => $overtime,
                'night_hours' => $nightHours,
                'night_allowance' => $nightAllowance,
                'holiday_hours' => $holidayHours,
                'holiday_allowance' => $holidayAllowance,
                'shift_allowance' => $shiftAllowance,
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
                'deductions' => $deductions,
                'net' => $net,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function salaryComponentOverrides(HrmEmployee $employee): array
    {
        $overrides = [];
        if (Schema::hasTable('hrm_employee_salary_components')) {
            $rows = \Modules\Hrm\Entities\HrmEmployeeSalaryComponent::query()
                ->where('employee_id', $employee->id)
                ->get();
            foreach ($rows as $row) {
                $overrides[$row->component_code] = [
                    'amount' => $row->amount,
                    'percentage' => $row->percentage,
                    'formula' => $row->formula,
                    'enabled' => $row->is_active ?? true,
                ];
            }
        }

        return $overrides;
    }

    private function applicability(HrmEmployee $employee, string $field, string $engagement, array $exemptEngagements): bool
    {
        if (array_key_exists($field, $employee->getAttributes()) && $employee->{$field} !== null) {
            return (bool) $employee->{$field};
        }

        return ! in_array($engagement, $exemptEngagements, true);
    }

    private function componentAmount(
        HrmPayrollComponent $component,
        float $base,
        int $year,
        int $month,
        array $settings,
        int $insuredDependents,
        HrmEmployee $employee,
        ?float $overrideAmount = null
    ): float {
        if ($overrideAmount !== null) {
            return $overrideAmount;
        }

        $code = (string) $component->code;
        return match ($code) {
            'housing' => (float) ($settings['housing_allowance'] ?? 14000000),
            'bon' => (float) ($settings['bon_allowance'] ?? 18000000),
            'child' => (float) ($settings['child_allowance_per_child'] ?? 9495000) * $insuredDependents,
            'marital' => $employee->marital_status === 'married' ? (float) ($settings['marital_allowance'] ?? 5000000) : 0.0,
            default => (float) ($component->default_amount ?? 0),
        };
    }

    private function approvedHours(int $employeeId, string $type, int $year, int $month, string $mode = 'hours'): float
    {
        return 0.0;
    }

    private function applyLoanInstallments(int $employeeId, int $runId): array
    {
        return [0.0, 0.0];
    }

    private function progressiveTax(float $taxable, array $brackets): float
    {
        return HrmTaxBrackets::calculateTaxMonthly($taxable, $brackets);
    }

    private function syncLoanBalance(HrmLoan $loan): void
    {
    }
}

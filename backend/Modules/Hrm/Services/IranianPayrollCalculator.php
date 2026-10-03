<?php

namespace Modules\Hrm\Services;

use Illuminate\Support\Facades\Schema;
use Modules\Hrm\Entities\HrmDependent;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmLoan;
use Modules\Hrm\Entities\HrmLoanInstallment;
use Modules\Hrm\Entities\HrmEmployeeSalary;
use Modules\Hrm\Entities\HrmPayrollComponent;
use Modules\Hrm\Entities\HrmPayrollSetting;
use Modules\Hrm\Entities\HrmRequest;

/**
 * Configurable Iranian payroll:
 * SSO worker 7% / employer 20% / unemployment 3%, overtime 40% (multiplier 1.4),
 * monthly tax ladder + wage floors defaulted to official 1405 (۱۴۰۵) figures in Rials.
 * Article 137-style deduction uses insurance_deductible_fraction (default 2/7).
 * All rates live in hrm_payroll_settings and can be changed by HR.
 */
class IranianPayrollCalculator
{
    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        // Official year 1405 (۱۴۰۵) statutory defaults in Rials — overridable via hrm_payroll_settings.
        return [
            'law_year' => 1405,
            'law_year_label' => '۱۴۰۵',
            'employee_insurance_percent' => 7,
            'employer_insurance_percent' => 20,
            'unemployment_insurance_percent' => 3,
            'insurance_ceiling' => 0,
            'overtime_multiplier' => 1.4,
            'monthly_hours' => 220,
            'mission_daily_allowance' => 0,
            'minimum_monthly_wage' => 166255500,
            'minimum_daily_wage' => 5541850,
            'minimum_hourly_wage' => 756050,
            'working_days_per_month' => 30,
            'seniority_daily_rate' => 166667,
            'seniority_monthly_amount' => 5000000,
            'min_wage_engagements' => ['full_time'],
            'apply_benefits_engagements' => ['full_time', 'part_time', 'remote'],
            'eydi_month' => 12,
            'eydi_months_factor' => 2,
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

        $overtimeHours = $this->approvedHours($employee->id, 'overtime', $year, $month);
        $monthlyHours = max(1, (float) ($settings['monthly_hours'] ?? 220));
        $hourly = $base > 0 ? $base / $monthlyHours : 0;
        $overtime = round($overtimeHours * $hourly * (float) ($settings['overtime_multiplier'] ?? 1.4), 2);

        $missionDays = $this->approvedHours($employee->id, 'mission', $year, $month, 'days');
        $missionAmount = $this->approvedHours($employee->id, 'mission', $year, $month, 'amount');
        $mission = round($missionAmount + ($missionDays * (float) ($settings['mission_daily_allowance'] ?? 0)), 2);

        $earnings = round(array_sum(array_column($lines, 'amount')) + $overtime + $mission, 2);
        $gross = round($base + $earnings, 2);
        if ($insuranceOn) {
            $insurable += $overtime + $mission;
        }
        if ($taxOn) {
            $taxableEarnings += $overtime + $mission;
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
     * @param  array<string, mixed>  $settings
     */

    /**
     * @param  list<string>  $defaultOff
     */
    private function applicability(HrmEmployee $employee, string $column, string $engagement, array $defaultOff): bool
    {
        if (Schema::hasColumn('hrm_employees', $column) && $employee->getAttribute($column) !== null) {
            return (bool) $employee->getAttribute($column);
        }

        return ! in_array($engagement, $defaultOff, true);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function salaryComponentOverrides(HrmEmployee $employee): array
    {
        if (! Schema::hasTable('hrm_employee_salaries')) {
            return [];
        }
        $row = HrmEmployeeSalary::query()
            ->where('employee_id', $employee->id)
            ->where(function ($q) {
                $q->whereNull('effective_from')->orWhere('effective_from', '<=', now()->toDateString());
            })
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
        if (! $row || ! is_array($row->components)) {
            return [];
        }
        $map = [];
        foreach ($row->components as $item) {
            if (! is_array($item) || empty($item['code'])) {
                continue;
            }
            $map[(string) $item['code']] = $item;
        }

        return $map;
    }

    private function componentAmount(object $component, float $base, int $year, int $month, array $settings, int $insuredDependents, ?HrmEmployee $employee = null, ?float $overrideAmount = null): float
    {
        $calc = (string) ($component->calculation ?? 'fixed');
        $amount = $overrideAmount ?? (float) $component->default_amount;
        $code = (string) ($component->code ?? '');

        if ($calc === 'percent_base') {
            return round($base * $amount / 100, 2);
        }
        if ($calc === 'per_insured_dependent') {
            return round($amount * $insuredDependents, 2);
        }
        if ($calc === 'marital' || $code === 'marital') {
            if (! $employee || ! $this->isMarried($employee)) {
                return 0.0;
            }

            return round($amount > 0 ? $amount : 5000000, 2);
        }
        if ($calc === 'eydi' || $code === 'eydi') {
            $eydiMonth = (int) ($settings['eydi_month'] ?? 12);
            if ($month !== $eydiMonth) {
                return 0.0;
            }
            if ($amount > 0) {
                return round($amount, 2);
            }

            return $this->eydiAmount($base, $settings, $employee);
        }
        if ($calc === 'severance' || $code === 'severance') {
            // Only when employee is terminating this period (decree_type exit/termination in payload month).
            if (! $employee || ! $this->isTerminatingThisMonth($employee, $year, $month)) {
                return 0.0;
            }

            return $this->severanceAmount($employee, $base, $settings);
        }

        return round($amount, 2);
    }

    /**
     * عیدی: factor×wage capped at cap_min_wage_months×minimum, prorated by months worked in year.
     */
    public function eydiAmount(float $base, array $settings, ?HrmEmployee $employee = null): float
    {
        $factor = (float) ($settings['eydi_months_factor'] ?? 2);
        $capMonths = (float) ($settings['eydi_cap_min_wage_months'] ?? 3);
        $minimum = (float) ($settings['minimum_monthly_wage'] ?? 0);
        $raw = $base * $factor;
        if ($minimum > 0 && $capMonths > 0) {
            $raw = min($raw, $minimum * $capMonths);
        }
        if (! ($settings['eydi_prorate'] ?? true) || ! $employee) {
            return round($raw, 2);
        }
        $months = $this->monthsWorkedInLawYear($employee, $settings);

        return round($raw * min(12, max(0, $months)) / 12, 2);
    }

    /**
     * سنوات پایان خدمت: severance_months_per_year × last monthly wage × years (prorated months/12).
     */
    public function severanceAmount(HrmEmployee $employee, float $base, array $settings): float
    {
        $perYear = (float) ($settings['severance_months_per_year'] ?? 1);
        $months = $this->totalMonthsOfService($employee);
        if ($months <= 0) {
            return 0.0;
        }
        if ($settings['severance_prorate'] ?? true) {
            $years = $months / 12;
        } else {
            $years = floor($months / 12);
        }

        return round($base * $perYear * $years, 2);
    }

    private function isMarried(HrmEmployee $employee): bool
    {
        $profile = $employee->relationLoaded('profile') ? $employee->profile : $employee->profile()->first();
        $custom = $profile?->custom_fields ?? [];
        if (array_key_exists('is_married', $custom)) {
            return (bool) $custom['is_married'];
        }
        if (array_key_exists('marital_status', $custom)) {
            return in_array((string) $custom['marital_status'], ['married', 'متاهل', '1', 'true'], true);
        }

        return false;
    }

        private function isTerminatingThisMonth(HrmEmployee $employee, int $year, int $month): bool
    {
        if (! Schema::hasTable('hrm_employment_decrees')) {
            return in_array((string) ($employee->contract_status ?? ''), ['terminated', 'resigned', 'end_of_service'], true);
        }
        $decree = \Modules\Hrm\Entities\HrmEmploymentDecree::query()
            ->where('employee_id', $employee->id)
            ->whereIn('decree_type', ['termination', 'exit', 'end_of_service', 'resign'])
            ->orderByDesc('id')
            ->first();
        if (! $decree) {
            return in_array((string) ($employee->contract_status ?? ''), ['terminated', 'resigned', 'end_of_service'], true);
        }
        if ($decree->effective_from) {
            return (int) $decree->effective_from->format('Y') === $year
                && (int) $decree->effective_from->format('n') === $month;
        }

        return true;
    }

    private function monthsWorkedInLawYear(HrmEmployee $employee, array $settings): float
    {
        $hire = $employee->hire_date;
        if (! $hire) {
            return 12.0;
        }
        // Approximate: Iranian law year 1405 ~ 2026-03-21 to 2027-03-20; for payroll year field we use hire vs now.
        $start = $hire->copy();
        $end = now();
        if ($employee->contract_end_date && $employee->contract_end_date->lt($end)) {
            $end = $employee->contract_end_date;
        }
        if ($end->lt($start)) {
            return 0.0;
        }
        $months = $start->diffInMonths($end) + 1;

        return (float) min(12, max(0, $months));
    }

    private function totalMonthsOfService(HrmEmployee $employee): float
    {
        $hire = $employee->hire_date;
        if (! $hire) {
            return 0.0;
        }
        $end = $employee->contract_end_date ?: now();
        if ($end->lt($hire)) {
            return 0.0;
        }

        return (float) ($hire->diffInMonths($end) + 1);
    }

    private function approvedHours(int $employeeId, string $type, int $year, int $month, string $field = 'hours'): float
    {
        if (! Schema::hasTable('hrm_requests')) {
            return 0.0;
        }

        $total = 0.0;
        $rows = HrmRequest::query()
            ->where('employee_id', $employeeId)
            ->where('type', $type)
            ->where('status', 'approved')
            ->get();

        foreach ($rows as $row) {
            $payload = $row->payload ?? [];
            $y = (int) ($payload['year'] ?? 0);
            $m = (int) ($payload['month'] ?? 0);
            if ($y && $m) {
                if ($y !== $year || $m !== $month) {
                    continue;
                }
            } elseif (! empty($payload['date'])) {
                $prefix = sprintf('%04d-%02d', $year, $month);
                if (! str_starts_with((string) $payload['date'], $prefix)) {
                    continue;
                }
            }
            $total += (float) ($payload[$field] ?? 0);
        }

        return $total;
    }

    /**
     * @param  list<array<string, mixed>>  $brackets
     */
    private function progressiveTax(float $taxable, array $brackets): float
    {
        if ($taxable <= 0) {
            return 0.0;
        }

        $tax = 0.0;
        $prev = 0.0;
        foreach ($brackets as $band) {
            if (! is_array($band)) {
                continue;
            }
            $up = $band['up_to'] ?? null;
            $rate = ((float) ($band['rate'] ?? 0)) / 100;
            $cap = $up === null ? $taxable : (float) $up;
            $slice = min($taxable, $cap) - $prev;
            if ($slice > 0) {
                $tax += $slice * $rate;
            }
            if ($up === null || $taxable <= $cap) {
                break;
            }
            $prev = $cap;
        }

        return round($tax, 2);
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function applyLoanInstallments(int $employeeId, int $runId): array
    {
        if (! Schema::hasTable('hrm_loans')) {
            return [0.0, 0.0];
        }

        $loanTotal = 0.0;
        $advanceTotal = 0.0;
        $loans = HrmLoan::query()
            ->where('employee_id', $employeeId)
            ->where('status', 'active')
            ->orderBy('id')
            ->get();

        foreach ($loans as $loan) {
            $paid = 0.0;
            if (Schema::hasTable('hrm_loan_installments')) {
                $paid = (float) HrmLoanInstallment::query()->where('loan_id', $loan->id)->sum('amount');
            }
            $remaining = round((float) $loan->principal - $paid, 2);
            if ($remaining <= 0) {
                $this->syncLoanBalance($loan);
                continue;
            }
            $due = round(min((float) $loan->installment_amount, $remaining), 2);
            if ($due <= 0) {
                continue;
            }
            HrmLoanInstallment::query()->create([
                'loan_id' => $loan->id,
                'payroll_run_id' => $runId,
                'amount' => $due,
                'paid_on' => now()->toDateString(),
                'status' => 'paid',
            ]);
            $this->syncLoanBalance($loan->fresh());
            if ($loan->type === 'advance') {
                $advanceTotal += $due;
            } else {
                $loanTotal += $due;
            }
        }

        return [round($loanTotal, 2), round($advanceTotal, 2)];
    }

    private function syncLoanBalance(HrmLoan $loan): void
    {
        $paid = (float) HrmLoanInstallment::query()->where('loan_id', $loan->id)->sum('amount');
        $count = (int) HrmLoanInstallment::query()->where('loan_id', $loan->id)->count();
        $remaining = max(0, round((float) $loan->principal - $paid, 2));
        $status = $loan->status === 'cancelled' ? 'cancelled' : ($remaining <= 0 ? 'settled' : 'active');
        $loan->update([
            'remaining_balance' => $remaining,
            'installments_paid' => $count,
            'status' => $status,
        ]);
    }
}

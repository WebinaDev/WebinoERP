<?php

namespace Modules\Hrm\Services;

use Modules\Hrm\Entities\HrmDecreeFormulaComponent;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmPayrollSetting;

class HrmFormulaEvaluationService
{
    /**
     * Evaluate dynamic formula expressions for decree wage components.
     * Supported variables:
     * - BASE: Base monthly salary
     * - DAILY: Daily wage (BASE / 30)
     * - EXP_YEARS: Years of experience or company tenure
     * - CHILDREN_COUNT: Number of dependents
     * - RANK: Employee rank or job grade
     * - MIN_WAGE: Minimum statutory wage (1405 law)
     *
     * @param string $formula
     * @param array<string, float|int> $context
     * @return float
     */
    public function evaluateComponent(string $formula, array $context): float
    {
        $base = (float) ($context['BASE'] ?? 0);
        $daily = (float) ($context['DAILY'] ?? ($base > 0 ? $base / 30 : 0));
        $expYears = (float) ($context['EXP_YEARS'] ?? 0);
        $children = (float) ($context['CHILDREN_COUNT'] ?? 0);
        $rank = (float) ($context['RANK'] ?? 1);
        $minWage = (float) ($context['MIN_WAGE'] ?? 166255500);

        $expression = strtr($formula, [
            'BASE' => (string) $base,
            'DAILY' => (string) $daily,
            'EXP_YEARS' => (string) $expYears,
            'CHILDREN_COUNT' => (string) $children,
            'RANK' => (string) $rank,
            'MIN_WAGE' => (string) $minWage,
        ]);

        // Security sanitize: allow only numbers, arithmetic operators and parentheses
        $sanitized = preg_replace('/[^0-9\.\+\-\*\/\(\)\s]/', '', $expression);

        if (empty($sanitized)) {
            return 0.0;
        }

        try {
            $result = @eval("return (float)({$sanitized});");
            return is_numeric($result) ? max(0.0, round((float) $result, 2)) : 0.0;
        } catch (\Throwable) {
            return 0.0;
        }
    }

    /**
     * Compute all active dynamic formula components for an employee decree
     *
     * @return array<string, array{title: string, amount: float, is_taxable: bool, is_insurable: bool}>
     */
    public function computeDecreeComponents(HrmEmployee $employee, float $baseSalary, ?int $expYears = null): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('hrm_decree_formula_components')) {
            return [];
        }

        $components = HrmDecreeFormulaComponent::query()->where('is_active', true)->get();
        if ($components->isEmpty()) {
            return [];
        }

        // Determine experience years
        if ($expYears === null) {
            $hireDate = $employee->hire_date ?? now();
            $expYears = (int) max(1, $hireDate->diffInYears(now()));
        }

        // Min statutory wage from 1405 settings or defaults
        $minWage = 166255500.0;
        if (\Illuminate\Support\Facades\Schema::hasTable('hrm_payroll_settings')) {
            $setting = HrmPayrollSetting::query()->first();
            if ($setting && ! empty($setting->minimum_monthly_wage)) {
                $minWage = (float) $setting->minimum_monthly_wage;
            }
        }

        $context = [
            'BASE' => $baseSalary,
            'DAILY' => round($baseSalary / 30, 2),
            'EXP_YEARS' => $expYears,
            'CHILDREN_COUNT' => $employee->dependents()->count() ?? 0,
            'RANK' => (int) ($employee->rank_level ?? 1),
            'MIN_WAGE' => $minWage,
        ];

        $results = [];
        foreach ($components as $component) {
            if ($component->calculation_type === 'formula' && ! empty($component->formula_expression)) {
                $results[$component->code] = [
                    'title' => $component->title,
                    'amount' => $this->evaluateComponent($component->formula_expression, $context),
                    'is_taxable' => (bool) $component->is_taxable,
                    'is_insurable' => (bool) $component->is_insurable,
                ];
            }
        }

        return $results;
    }
}

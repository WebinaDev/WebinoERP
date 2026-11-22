<?php

namespace Modules\Hrm\Services;

use Modules\Hrm\Entities\HrmDecreeFormulaComponent;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmPayrollSetting;

class HrmFormulaEvaluationService
{
    public function __construct(
        private readonly ?HrmExpressionParserService $parser = null
    ) {}

    /**
     * Compute expression with all extended workforce variables
     *
     * @param string $formula
     * @param array<string, float|int|string|bool> $context
     * @return float
     */
    public function evaluateComponent(string $formula, array $context): float
    {
        $parser = $this->parser ?: new HrmExpressionParserService();

        return $parser->evaluate($formula, $context);
    }

    /**
     * Build rich context map for an employee and calculate all active formula components
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

        if ($expYears === null) {
            $hireDate = $employee->hire_date ?? now();
            $expYears = (int) max(1, $hireDate->diffInYears(now()));
        }

        $minWage = 166255500.0;
        if (\Illuminate\Support\Facades\Schema::hasTable('hrm_payroll_settings')) {
            $setting = HrmPayrollSetting::query()->first();
            if ($setting && ! empty($setting->minimum_monthly_wage)) {
                $minWage = (float) $setting->minimum_monthly_wage;
            }
        }

        // Expanded workforce parameters
        $context = [
            'BASE' => $baseSalary,
            'DAILY' => round($baseSalary / 30, 2),
            'EXP_YEARS' => $expYears,
            'SENIORITY_YEARS' => $expYears,
            'CHILDREN_COUNT' => $employee->dependents()->count() ?? 0,
            'INSURED_CHILDREN' => $employee->dependents()->where('is_insured', true)->count() ?? 0,
            'RANK' => (int) ($employee->rank_level ?? 1),
            'JOB_GRADE' => (int) ($employee->rank_level ?? 1),
            'MIN_WAGE' => $minWage,
            'MARITAL_STATUS' => $employee->marital_status === 'married' ? 1 : 0,
            'ENGAGEMENT_TYPE' => $employee->engagement_type ?? 'permanent',
            'OVERTIME_HOURS' => 0,
            'SHIFT_DAYS' => 30,
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

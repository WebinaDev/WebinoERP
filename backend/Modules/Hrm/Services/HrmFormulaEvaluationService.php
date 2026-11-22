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
     * Safely evaluate expression using HrmExpressionParserService (RPN / Shunting Yard parser)
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

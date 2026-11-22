<?php

namespace Modules\Hrm\Services;

use Modules\Hrm\Entities\HrmDecreeFormulaComponent;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmEmploymentDecree;

class HrmFormulaEvaluationService
{
    /**
     * Evaluate dynamic formula expressions for decree wage components.
     * Supported variables: BASE, DAILY, EXP_YEARS, RANK, CHILDREN_COUNT
     */
    public function evaluateComponent(string $formula, array $context): float
    {
        // Safe variable replacement
        $base = (float) ($context['BASE'] ?? 0);
        $daily = (float) ($context['DAILY'] ?? ($base / 30));
        $expYears = (float) ($context['EXP_YEARS'] ?? 0);
        $children = (float) ($context['CHILDREN_COUNT'] ?? 0);
        $rank = (float) ($context['RANK'] ?? 1);

        $expression = strtr($formula, [
            'BASE' => (string) $base,
            'DAILY' => (string) $daily,
            'EXP_YEARS' => (string) $expYears,
            'CHILDREN_COUNT' => (string) $children,
            'RANK' => (string) $rank,
        ]);

        // Security sanitize: allow only numbers, arithmetic operators and parentheses
        $sanitized = preg_replace('/[^0-9\.\+\-\*\/\(\)\s]/', '', $expression);

        if (empty($sanitized)) {
            return 0.0;
        }

        try {
            // Evaluate math string safely
            $result = @eval("return (float)({$sanitized});");
            return is_numeric($result) ? round((float) $result, 2) : 0.0;
        } catch (\Throwable) {
            return 0.0;
        }
    }

    /**
     * Compute all formula components for an employee decree
     *
     * @return array<string, float>
     */
    public function computeDecreeComponents(HrmEmployee $employee, float $baseSalary, int $expYears = 3): array
    {
        $components = \Illuminate\Support\Facades\Schema::hasTable('hrm_decree_formula_components')
            ? HrmDecreeFormulaComponent::query()->where('is_active', true)->get()
            : collect();

        $context = [
            'BASE' => $baseSalary,
            'DAILY' => round($baseSalary / 30, 2),
            'EXP_YEARS' => $expYears,
            'CHILDREN_COUNT' => $employee->dependents()->count() ?? 0,
            'RANK' => 1,
        ];

        $results = [];
        foreach ($components as $component) {
            if ($component->calculation_type === 'formula' && ! empty($component->formula_expression)) {
                $results[$component->code] = [
                    'title' => $component->title,
                    'amount' => $this->evaluateComponent($component->formula_expression, $context),
                    'is_taxable' => $component->is_taxable,
                    'is_insurable' => $component->is_insurable,
                ];
            }
        }

        return $results;
    }
}

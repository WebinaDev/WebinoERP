<?php

namespace Modules\Hrm\Services;

use Illuminate\Support\Facades\Schema;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmPayrollItem;
use Modules\Hrm\Entities\HrmWorkforceBudget;
use Modules\Hrm\Support\HrmPeriod;

/**
 * Workforce budget vs actual per department.
 *
 * Periods use the payroll convention: Jalali year/month (e.g. 1405/7). A budget row with an
 * empty month is annual. With a month selected, a monthly row wins; otherwise the annual cost
 * budget is prorated (÷12). Actual cost = gross + employer insurance from calculated/approved/paid
 * payroll runs. Actual headcount = active employees in the department today.
 */
class HrmWorkforceBudgetService
{
    /**
     * @return array{year: int, month: ?int, rows: list<array<string, mixed>>, totals: array<string, float|int>}
     */
    public function compare(?int $year = null, ?int $month = null): array
    {
        $year ??= HrmPeriod::currentJalali()[0];
        $month = $month ?: null;
        $empty = ['headcount_budget' => 0, 'headcount_actual' => 0, 'cost_budget' => 0.0, 'cost_actual' => 0.0];
        if (! Schema::hasTable('hrm_workforce_budgets')) {
            return ['year' => $year, 'month' => $month, 'rows' => [], 'totals' => $empty];
        }
        $budgets = HrmWorkforceBudget::query()->where('year', $year)->get();
        $annual = $budgets->filter(fn ($b) => ! $b->month)->keyBy('department');
        $monthly = $month ? $budgets->filter(fn ($b) => (int) $b->month === $month)->keyBy('department') : collect();

        $departments = $budgets->pluck('department')
            ->merge(HrmEmployee::query()->whereNotNull('department')->where('department', '!=', '')->distinct()->pluck('department'))
            ->filter()->unique()->sort()->values();

        $headcounts = HrmEmployee::query()->where('status', 'active')->whereNotNull('department')
            ->selectRaw('department, count(*) as c')->groupBy('department')->pluck('c', 'department');
        $costs = $this->actualCosts($year, $month);

        $rows = [];
        $totals = $empty;
        foreach ($departments as $department) {
            $row = $monthly->get($department);
            $basis = 'monthly';
            $costBudget = $row ? (float) $row->cost_budget : null;
            $headBudget = $row ? (int) $row->headcount : null;
            if (! $row && ($a = $annual->get($department))) {
                $basis = $month ? 'annual_prorated' : 'annual';
                $costBudget = $month ? round((float) $a->cost_budget / 12, 2) : (float) $a->cost_budget;
                $headBudget = (int) $a->headcount;
                $row = $a;
            }
            if (! $row) {
                $basis = 'none';
            }
            $headActual = (int) ($headcounts[$department] ?? 0);
            $costActual = (float) ($costs[$department] ?? 0);
            $rows[] = [
                'department' => $department,
                'basis' => $basis,
                'budget_id' => $row?->id,
                'headcount_budget' => $headBudget ?? 0,
                'headcount_actual' => $headActual,
                'headcount_variance' => $headActual - ($headBudget ?? 0),
                'cost_budget' => $costBudget ?? 0.0,
                'cost_actual' => $costActual,
                'cost_variance' => round($costActual - ($costBudget ?? 0), 2),
                'cost_usage_percent' => $costBudget ? round($costActual * 100 / $costBudget, 1) : null,
            ];
            $totals['headcount_budget'] += $headBudget ?? 0;
            $totals['headcount_actual'] += $headActual;
            $totals['cost_budget'] += $costBudget ?? 0;
            $totals['cost_actual'] += $costActual;
        }

        return ['year' => $year, 'month' => $month, 'rows' => $rows, 'totals' => $totals];
    }

    /**
     * @return array<string, float>
     */
    private function actualCosts(int $year, ?int $month): array
    {
        if (! Schema::hasTable('hrm_payroll_items')) {
            return [];
        }
        $items = HrmPayrollItem::query()
            ->with('employee:id,department')
            ->whereHas('payrollRun', function ($q) use ($year, $month) {
                $q->where('year', $year)->whereIn('status', ['calculated', 'approved', 'paid']);
                if ($month) {
                    $q->where('month', $month);
                }
            })
            ->get();
        $out = [];
        foreach ($items as $item) {
            $dept = (string) ($item->employee?->department ?? '');
            if ($dept === '') {
                continue;
            }
            $b = is_array($item->breakdown) ? $item->breakdown : [];
            $out[$dept] = ($out[$dept] ?? 0) + (float) $item->gross + (float) ($b['employer_insurance'] ?? 0);
        }

        return array_map(fn ($v) => round($v, 2), $out);
    }
}

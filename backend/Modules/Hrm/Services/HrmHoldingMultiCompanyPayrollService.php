<?php

namespace Modules\Hrm\Services;

use Modules\Hrm\Entities\HrmPayrollItem;
use Modules\Hrm\Entities\HrmPayrollRun;

class HrmHoldingMultiCompanyPayrollService
{
    /**
     * Consolidate payroll reporting across multi-subsidiary enterprise holdings:
     * - Centralized HR policies
     * - Semi-centralized (shared payroll processing with subsidiary-specific bank/insurance)
     * - Independent subsidiary operations
     *
     * @return array{
     *   holding_summary: array<string, mixed>,
     *   subsidiaries: list<array<string, mixed>>
     * }
     */
    public function generateHoldingConsolidatedReport(int $year, int $month): array
    {
        $runs = HrmPayrollRun::query()
            ->where('year', $year)
            ->where('month', $month)
            ->with(['items'])
            ->get();

        $totalStaff = 0;
        $totalGross = 0.0;
        $totalNet = 0.0;
        $totalSso = 0.0;
        $totalTax = 0.0;

        $subsidiaryReports = [];

        foreach ($runs as $run) {
            $staffCount = $run->items->count();
            $gross = (float) $run->items->sum('gross');
            $net = (float) $run->items->sum('net');
            $sso = (float) $run->items->sum('employee_insurance') + (float) $run->items->sum('employer_insurance');
            $tax = (float) $run->items->sum('tax');

            $totalStaff += $staffCount;
            $totalGross += $gross;
            $totalNet += $net;
            $totalSso += $sso;
            $totalTax += $tax;

            $subsidiaryReports[] = [
                'payroll_run_id' => $run->id,
                'run_title' => $run->title,
                'staff_count' => $staffCount,
                'gross' => $gross,
                'net' => $net,
                'sso' => $sso,
                'tax' => $tax,
                'workflow_state' => $run->workflow_state ?? 'locked',
            ];
        }

        return [
            'holding_summary' => [
                'year' => $year,
                'month' => $month,
                'total_companies_reported' => count($runs),
                'total_holding_staff' => $totalStaff,
                'total_holding_gross' => $totalGross,
                'total_holding_net' => $totalNet,
                'total_holding_sso' => $totalSso,
                'total_holding_tax' => $totalTax,
            ],
            'subsidiaries' => $subsidiaryReports,
        ];
    }
}

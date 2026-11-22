<?php

namespace Modules\Hrm\Services;

use Modules\Hrm\Entities\HrmPayrollItem;
use Modules\Hrm\Entities\HrmPayrollRun;
use Modules\Hrm\Entities\HrmWorkshop;

class HrmHoldingMultiCompanyPayrollService
{
    /**
     * Holding Governance Models:
     * - centralized: Single holding HQ manages uniform payroll policy & disbursement
     * - semi_centralized: HQ processes calculation, subsidiaries disburse with independent bank/SSO accounts
     * - decentralized: Independent subsidiary company operations with top-level financial rollups
     *
     * @return array{
     *   holding_summary: array<string, mixed>,
     *   governance_model: string,
     *   companies: list<array<string, mixed>>
     * }
     */
    public function generateHoldingConsolidatedReport(int $year, int $month, string $governanceModel = 'semi_centralized'): array
    {
        $workshops = \Illuminate\Support\Facades\Schema::hasTable('hrm_workshops')
            ? HrmWorkshop::query()->where('is_active', true)->get()
            : collect();

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

        $companyReports = [];

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

            // Associate company metadata if workshop assigned
            $workshop = $workshops->firstWhere('company_code', $run->company_code ?? 'MAIN');

            $companyReports[] = [
                'company_code' => $workshop?->company_code ?? 'MAIN',
                'company_name' => $workshop?->company_name ?? ($run->title ?: 'شرکت تابعه'),
                'workshop_code' => $workshop?->workshop_code ?? 'دفتر مرکزی',
                'payroll_run_id' => $run->id,
                'run_title' => $run->title,
                'staff_count' => $staffCount,
                'gross' => $gross,
                'net' => $net,
                'sso' => $sso,
                'tax' => $tax,
                'disbursement_policy' => $governanceModel === 'centralized' ? 'حواله متمرکز هلدینگ' : 'حساب اختصاصی شرکت تابعه',
                'workflow_state' => $run->workflow_state ?? 'locked',
            ];
        }

        return [
            'holding_summary' => [
                'year' => $year,
                'month' => $month,
                'governance_model' => $governanceModel,
                'total_companies_reported' => count($companyReports),
                'total_holding_staff' => $totalStaff,
                'total_holding_gross' => $totalGross,
                'total_holding_net' => $totalNet,
                'total_holding_sso' => $totalSso,
                'total_holding_tax' => $totalTax,
            ],
            'governance_model' => $governanceModel,
            'companies' => $companyReports,
        ];
    }
}

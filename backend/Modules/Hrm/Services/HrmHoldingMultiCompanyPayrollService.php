<?php

namespace Modules\Hrm\Services;

use Modules\Hrm\Entities\HrmPayrollItem;
use Modules\Hrm\Entities\HrmPayrollRun;
use Modules\Hrm\Entities\HrmSubsidiaryCompany;

class HrmHoldingMultiCompanyPayrollService
{
    /**
     * Consolidate payroll reporting across multi-subsidiary enterprise holdings
     * by querying real subsidiary entities (`HrmSubsidiaryCompany`).
     *
     * @return array{
     *   holding_summary: array<string, mixed>,
     *   governance_model: string,
     *   companies: list<array<string, mixed>>
     * }
     */
    public function generateHoldingConsolidatedReport(int $year, int $month, string $governanceModel = 'semi_centralized'): array
    {
        $companies = \Illuminate\Support\Facades\Schema::hasTable('hrm_subsidiary_companies')
            ? HrmSubsidiaryCompany::query()->where('is_active', true)->get()
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

            $subsidiary = $run->subsidiary_company_id
                ? $companies->firstWhere('id', $run->subsidiary_company_id)
                : $companies->first();

            $companyReports[] = [
                'company_id' => $subsidiary?->id,
                'company_code' => $subsidiary?->code ?? 'HQ_MAIN',
                'company_name' => $subsidiary?->name ?? ($run->title ?: 'شرکت تابعه هلدینگ'),
                'workshop_code' => $subsidiary?->workshop_code ?? 'نامشخص',
                'company_sheba' => $subsidiary?->company_sheba ?? 'تعریف نشده',
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

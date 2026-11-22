<?php

namespace Modules\Hrm\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Modules\Hrm\Entities\HrmAttendanceRecord;
use Modules\Hrm\Entities\HrmDepartment;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmEmploymentDecree;
use Modules\Hrm\Entities\HrmLoan;
use Modules\Hrm\Entities\HrmLoanInstallment;
use Modules\Hrm\Entities\HrmOrgStructureHistory;
use Modules\Hrm\Entities\HrmPayrollItem;
use Modules\Hrm\Entities\HrmPayrollRun;
use Modules\Hrm\Entities\HrmSubsidiaryCompany;
use Modules\Hrm\Entities\HrmTimesheet;

class HrmManagementReportService
{
    /**
     * Apply common dimensional filters (month/year, department, subsidiary company, contract/engagement type)
     */
    private function applyEmployeeFilters(Builder $query, array $filters, string $employeeRelationPrefix = ''): Builder
    {
        $prefix = $employeeRelationPrefix !== '' ? rtrim($employeeRelationPrefix, '.') . '.' : '';

        if (! empty($filters['department_id'])) {
            $query->where("{$prefix}department_id", $filters['department_id']);
        }

        if (! empty($filters['subsidiary_company_id']) && Schema::hasColumn('hrm_employees', 'subsidiary_company_id')) {
            $query->where("{$prefix}subsidiary_company_id", $filters['subsidiary_company_id']);
        }

        if (! empty($filters['contract_type'])) {
            $query->where("{$prefix}contract_type", $filters['contract_type']);
        }

        if (! empty($filters['engagement_type'])) {
            $query->where("{$prefix}engagement_type", $filters['engagement_type']);
        }

        return $query;
    }

    /**
     * 1. Payroll List Report (گزارش لیست حقوق و دستمزد)
     * Filters: year, month, department_id, subsidiary_company_id, contract_type, engagement_type
     *
     * @return array{
     *   total_employees: int,
     *   total_gross: float,
     *   total_net: float,
     *   total_deductions: float,
     *   total_base_salary: float,
     *   items: list<array<string, mixed>>
     * }
     */
    public function getPayrollListReport(array $filters = []): array
    {
        $query = HrmPayrollItem::query()
            ->with(['employee.department', 'employee.subsidiaryCompany', 'payrollRun']);

        if (! empty($filters['year']) || ! empty($filters['month']) || ! empty($filters['subsidiary_company_id'])) {
            $query->whereHas('payrollRun', function (Builder $q) use ($filters) {
                if (! empty($filters['year'])) {
                    $q->where('year', $filters['year']);
                }
                if (! empty($filters['month'])) {
                    $q->where('month', $filters['month']);
                }
                if (! empty($filters['subsidiary_company_id']) && Schema::hasColumn('hrm_payroll_runs', 'subsidiary_company_id')) {
                    $q->where('subsidiary_company_id', $filters['subsidiary_company_id']);
                }
            });
        }

        $query->whereHas('employee', function (Builder $q) use ($filters) {
            $this->applyEmployeeFilters($q, $filters);
        });

        $items = $query->get();

        $rows = [];
        $totalGross = 0.0;
        $totalNet = 0.0;
        $totalDeductions = 0.0;
        $totalBase = 0.0;

        foreach ($items as $item) {
            $emp = $item->employee;
            $run = $item->payrollRun;
            $bd = is_array($item->breakdown) ? $item->breakdown : [];

            $gross = (float) $item->gross;
            $net = (float) $item->net;
            $ded = (float) $item->deductions;
            $base = (float) ($bd['base'] ?? $emp?->base_salary ?? 0);

            $totalGross += $gross;
            $totalNet += $net;
            $totalDeductions += $ded;
            $totalBase += $base;

            $rows[] = [
                'item_id' => $item->id,
                'run_id' => $item->payroll_run_id,
                'period' => $run ? "{$run->year}/" . sprintf('%02d', $run->month) : '-',
                'employee_id' => $emp?->id,
                'employee_code' => $emp?->employee_code,
                'employee_name' => $emp ? "{$emp->first_name} {$emp->last_name}" : '-',
                'department' => $emp?->department?->name ?? 'عمومی',
                'subsidiary_company' => $emp?->subsidiaryCompany?->name ?? $run?->subsidiaryCompany?->name ?? 'دفتر مرکزی',
                'contract_type' => $emp?->contract_type ?? 'permanent',
                'engagement_type' => $emp?->engagement_type ?? 'full_time',
                'base_salary' => $base,
                'gross' => $gross,
                'deductions' => $ded,
                'net' => $net,
                'overtime' => (float) ($bd['overtime'] ?? 0),
                'shift_allowance' => (float) ($bd['shift_allowance'] ?? 0),
                'mission' => (float) ($bd['mission'] ?? 0),
            ];
        }

        return [
            'total_employees' => count($rows),
            'total_base_salary' => round($totalBase, 2),
            'total_gross' => round($totalGross, 2),
            'total_deductions' => round($totalDeductions, 2),
            'total_net' => round($totalNet, 2),
            'items' => $rows,
        ];
    }

    /**
     * 2. Insurance and Tax Report (گزارش بیمه و مالیات)
     * Filters: year, month, department_id, subsidiary_company_id, contract_type
     *
     * @return array{
     *   total_employees: int,
     *   total_insurable: float,
     *   total_employee_insurance: float,
     *   total_employer_insurance: float,
     *   total_sso_combined: float,
     *   total_taxable: float,
     *   total_tax: float,
     *   items: list<array<string, mixed>>
     * }
     */
    public function getInsuranceAndTaxReport(array $filters = []): array
    {
        $query = HrmPayrollItem::query()
            ->with(['employee.department', 'employee.subsidiaryCompany', 'payrollRun']);

        if (! empty($filters['year']) || ! empty($filters['month']) || ! empty($filters['subsidiary_company_id'])) {
            $query->whereHas('payrollRun', function (Builder $q) use ($filters) {
                if (! empty($filters['year'])) {
                    $q->where('year', $filters['year']);
                }
                if (! empty($filters['month'])) {
                    $q->where('month', $filters['month']);
                }
                if (! empty($filters['subsidiary_company_id']) && Schema::hasColumn('hrm_payroll_runs', 'subsidiary_company_id')) {
                    $q->where('subsidiary_company_id', $filters['subsidiary_company_id']);
                }
            });
        }

        $query->whereHas('employee', function (Builder $q) use ($filters) {
            $this->applyEmployeeFilters($q, $filters);
        });

        $items = $query->get();

        $rows = [];
        $totalInsurable = 0.0;
        $totalEmployeeIns = 0.0;
        $totalEmployerIns = 0.0;
        $totalTaxable = 0.0;
        $totalTax = 0.0;

        foreach ($items as $item) {
            $emp = $item->employee;
            $run = $item->payrollRun;
            $bd = is_array($item->breakdown) ? $item->breakdown : [];

            $insurable = (float) ($item->insurable_wage ?: ($bd['insurable'] ?? $item->gross));
            $empIns = (float) ($item->employee_insurance ?: ($bd['employee_insurance'] ?? 0));
            $emprIns = (float) ($item->employer_insurance ?: ($bd['employer_insurance'] ?? 0));
            $taxable = (float) ($item->taxable_wage ?: ($bd['taxable'] ?? $item->gross));
            $tax = (float) ($item->tax ?: ($bd['tax'] ?? 0));

            $totalInsurable += $insurable;
            $totalEmployeeIns += $empIns;
            $totalEmployerIns += $emprIns;
            $totalTaxable += $taxable;
            $totalTax += $tax;

            $rows[] = [
                'employee_id' => $emp?->id,
                'employee_code' => $emp?->employee_code,
                'employee_name' => $emp ? "{$emp->first_name} {$emp->last_name}" : '-',
                'department' => $emp?->department?->name ?? 'عمومی',
                'subsidiary_company' => $emp?->subsidiaryCompany?->name ?? $run?->subsidiaryCompany?->name ?? 'دفتر مرکزی',
                'contract_type' => $emp?->contract_type ?? 'permanent',
                'national_id' => $emp?->national_id,
                'insurance_no' => $emp?->insurance_no ?: ($emp?->profile?->insurance_number ?? '-'),
                'insurable_wage' => $insurable,
                'employee_insurance' => $empIns,
                'employer_insurance' => $emprIns,
                'total_insurance' => round($empIns + $emprIns, 2),
                'taxable_wage' => $taxable,
                'tax' => $tax,
            ];
        }

        return [
            'total_employees' => count($rows),
            'total_insurable' => round($totalInsurable, 2),
            'total_employee_insurance' => round($totalEmployeeIns, 2),
            'total_employer_insurance' => round($totalEmployerIns, 2),
            'total_sso_combined' => round($totalEmployeeIns + $totalEmployerIns, 2),
            'total_taxable' => round($totalTaxable, 2),
            'total_tax' => round($totalTax, 2),
            'items' => $rows,
        ];
    }

    /**
     * 3. Attendance and Overtime Report (گزارش کارکرد و اضافه‌کاری)
     * Filters: year, month, department_id, subsidiary_company_id, contract_type
     *
     * @return array{
     *   total_records: int,
     *   total_hours_worked: float,
     *   total_overtime_hours: float,
     *   total_night_hours: float,
     *   total_holiday_hours: float,
     *   items: list<array<string, mixed>>
     * }
     */
    public function getAttendanceAndOvertimeReport(array $filters = []): array
    {
        $year = (int) ($filters['year'] ?? now()->year);
        $month = (int) ($filters['month'] ?? now()->month);

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = Carbon::create($year, $month, 1)->endOfMonth();

        $query = HrmTimesheet::query()
            ->with(['employee.department', 'employee.subsidiaryCompany'])
            ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()]);

        $query->whereHas('employee', function (Builder $q) use ($filters) {
            $this->applyEmployeeFilters($q, $filters);
        });

        $timesheets = $query->get();

        $grouped = $timesheets->groupBy('employee_id');
        $rows = [];

        $sumWorked = 0.0;
        $sumOvertime = 0.0;
        $sumNight = 0.0;
        $sumHoliday = 0.0;

        foreach ($grouped as $empId => $records) {
            $emp = $records->first()->employee;

            $totalWorked = (float) $records->sum('hours');
            $overtime = (float) $records->where('hour_type', 'overtime')->sum('hours');
            $night = (float) $records->where('hour_type', 'night')->sum('hours');
            $holiday = (float) $records->where('hour_type', 'holiday')->sum('hours');

            $sumWorked += $totalWorked;
            $sumOvertime += $overtime;
            $sumNight += $night;
            $sumHoliday += $holiday;

            $rows[] = [
                'employee_id' => $emp?->id,
                'employee_code' => $emp?->employee_code,
                'employee_name' => $emp ? "{$emp->first_name} {$emp->last_name}" : '-',
                'department' => $emp?->department?->name ?? 'عمومی',
                'subsidiary_company' => $emp?->subsidiaryCompany?->name ?? 'دفتر مرکزی',
                'contract_type' => $emp?->contract_type ?? 'permanent',
                'engagement_type' => $emp?->engagement_type ?? 'full_time',
                'total_worked_hours' => round($totalWorked, 2),
                'overtime_hours' => round($overtime, 2),
                'night_hours' => round($night, 2),
                'holiday_hours' => round($holiday, 2),
            ];
        }

        return [
            'total_records' => count($rows),
            'total_hours_worked' => round($sumWorked, 2),
            'total_overtime_hours' => round($sumOvertime, 2),
            'total_night_hours' => round($sumNight, 2),
            'total_holiday_hours' => round($sumHoliday, 2),
            'items' => $rows,
        ];
    }

    /**
     * 4. Decrees and Personnel Changes Report (گزارش احکام و تغییرات پرسنلی)
     * Filters: year, month, department_id, subsidiary_company_id, contract_type
     *
     * @return array{
     *   total_decrees: int,
     *   decrees: list<array<string, mixed>>,
     *   structural_changes: list<array<string, mixed>>
     * }
     */
    public function getDecreesAndPersonnelChangesReport(array $filters = []): array
    {
        $query = HrmEmploymentDecree::query()
            ->with(['employee.department', 'employee.subsidiaryCompany']);

        if (! empty($filters['year']) && ! empty($filters['month'])) {
            $start = Carbon::create($filters['year'], $filters['month'], 1)->startOfMonth();
            $end = Carbon::create($filters['year'], $filters['month'], 1)->endOfMonth();
            $query->whereBetween('effective_from', [$start->toDateString(), $end->toDateString()]);
        }

        $query->whereHas('employee', function (Builder $q) use ($filters) {
            $this->applyEmployeeFilters($q, $filters);
        });

        $decrees = $query->orderByDesc('effective_from')->get()->map(function ($d) {
            $emp = $d->employee;

            return [
                'id' => $d->id,
                'decree_no' => $d->decree_no,
                'decree_type' => $d->decree_type,
                'status' => $d->status,
                'effective_from' => optional($d->effective_from)?->toDateString(),
                'employee_id' => $emp?->id,
                'employee_code' => $emp?->employee_code,
                'employee_name' => $emp ? "{$emp->first_name} {$emp->last_name}" : '-',
                'department' => $emp?->department?->name ?? $d->department ?? 'عمومی',
                'subsidiary_company' => $emp?->subsidiaryCompany?->name ?? 'دفتر مرکزی',
                'contract_type' => $d->contract_type ?? $emp?->contract_type,
                'base_salary' => (float) $d->base_salary,
                'daily_wage' => (float) $d->daily_wage,
            ];
        })->all();

        // Structural history query
        $structQuery = HrmOrgStructureHistory::query()
            ->with(['employee.department', 'employee.subsidiaryCompany', 'position']);

        if (! empty($filters['year']) && ! empty($filters['month'])) {
            $start = Carbon::create($filters['year'], $filters['month'], 1)->startOfMonth();
            $end = Carbon::create($filters['year'], $filters['month'], 1)->endOfMonth();
            $structQuery->whereBetween('effective_from', [$start->toDateString(), $end->toDateString()]);
        }

        $structQuery->whereHas('employee', function (Builder $q) use ($filters) {
            $this->applyEmployeeFilters($q, $filters);
        });

        $structuralChanges = $structQuery->orderByDesc('effective_from')->get()->map(function ($h) {
            $emp = $h->employee;

            return [
                'id' => $h->id,
                'employee_id' => $emp?->id,
                'employee_name' => $emp ? "{$emp->first_name} {$emp->last_name}" : '-',
                'employee_code' => $emp?->employee_code,
                'position_title' => $h->position?->title ?? '-',
                'change_type' => $h->change_type,
                'change_type_label' => $h->change_type_label,
                'effective_from' => optional($h->effective_from)?->toDateString(),
                'decree_no' => $h->decree_no,
                'change_reason' => $h->change_reason,
            ];
        })->all();

        return [
            'total_decrees' => count($decrees),
            'decrees' => $decrees,
            'structural_changes' => $structuralChanges,
        ];
    }

    /**
     * 5. Loans and Deductions Report (گزارش وام و کسورات)
     * Filters: year, month, department_id, subsidiary_company_id, contract_type
     *
     * @return array{
     *   total_loans: int,
     *   total_principal: float,
     *   total_installments_paid_amount: float,
     *   total_remaining_balance: float,
     *   loans: list<array<string, mixed>>
     * }
     */
    public function getLoansAndDeductionsReport(array $filters = []): array
    {
        $query = HrmLoan::query()
            ->with(['employee.department', 'employee.subsidiaryCompany', 'installments']);

        $query->whereHas('employee', function (Builder $q) use ($filters) {
            $this->applyEmployeeFilters($q, $filters);
        });

        $loans = $query->get();

        $rows = [];
        $totalPrincipal = 0.0;
        $totalPaidAmount = 0.0;
        $totalRemaining = 0.0;

        foreach ($loans as $loan) {
            $emp = $loan->employee;
            $principal = (float) $loan->principal;
            $remaining = (float) $loan->remaining_balance;
            $paidAmount = max(0.0, round($principal - $remaining, 2));

            $totalPrincipal += $principal;
            $totalRemaining += $remaining;
            $totalPaidAmount += $paidAmount;

            $rows[] = [
                'loan_id' => $loan->id,
                'employee_id' => $emp?->id,
                'employee_code' => $emp?->employee_code,
                'employee_name' => $emp ? "{$emp->first_name} {$emp->last_name}" : '-',
                'department' => $emp?->department?->name ?? 'عمومی',
                'subsidiary_company' => $emp?->subsidiaryCompany?->name ?? 'دفتر مرکزی',
                'contract_type' => $emp?->contract_type ?? 'permanent',
                'title' => $loan->title,
                'type' => $loan->type,
                'status' => $loan->status,
                'principal' => $principal,
                'installment_amount' => (float) $loan->installment_amount,
                'installments_total' => (int) $loan->installments_total,
                'installments_paid' => (int) $loan->installments_paid,
                'paid_amount' => $paidAmount,
                'remaining_balance' => $remaining,
            ];
        }

        return [
            'total_loans' => count($rows),
            'total_principal' => round($totalPrincipal, 2),
            'total_installments_paid_amount' => round($totalPaidAmount, 2),
            'total_remaining_balance' => round($totalRemaining, 2),
            'loans' => $rows,
        ];
    }
}

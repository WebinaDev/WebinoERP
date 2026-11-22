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

    /**
     * Pre-Approval Control Reports (گزارش‌های کنترلی پیش از تأیید نهایی دوره حقوق):
     * 1. لیست مغایرت بیمه (افراد بدون شماره بیمه یا کد ملی نامعتبر)
     * 2. لیست مغایرت بانکی (شبا نامعتبر یا خالی)
     * 3. خلاصه اضافه‌کاری و نوبت‌کاری غیرعادی
     * 4. لیست معوقات و تأثیر آن‌ها روی دوره جاری
     * 5. مقایسه جمع ناخالص / کسور / خالص با دوره قبل
     *
     * @return array{
     *   run_id: int,
     *   period: string,
     *   is_ready_for_approval: bool,
     *   insurance_discrepancies: array{count: int, items: list<array<string, mixed>>},
     *   banking_discrepancies: array{count: int, items: list<array<string, mixed>>},
     *   abnormal_attendance: array{count: int, items: list<array<string, mixed>>},
     *   retro_adjustments: array{count: int, total_amount: float, items: list<array<string, mixed>>},
     *   period_comparison: array{
     *     current: array{gross: float, deductions: float, net: float, employee_count: int},
     *     previous: array{gross: float, deductions: float, net: float, employee_count: int},
     *     difference: array{gross: float, deductions: float, net: float, employee_count: int},
     *     percentage_change: array{gross: float, net: float}
     *   }
     * }
     */
    public function getPayrollControlAuditReports(HrmPayrollRun $run, array $thresholds = []): array
    {
        $run->loadMissing(['items.employee.profile']);
        $overtimeThreshold = (float) ($thresholds['overtime_hours_threshold'] ?? 120.0); // e.g. >120 hours/month abnormal
        $shiftThreshold = (float) ($thresholds['shift_allowance_threshold'] ?? 22.5); // statutory max shift % is 22.5%

        $insuranceDiscrepancies = [];
        $bankingDiscrepancies = [];
        $abnormalAttendance = [];
        $retroItems = [];

        $currentGross = 0.0;
        $currentDeductions = 0.0;
        $currentNet = 0.0;
        $currentCount = 0;
        $totalRetroAmount = 0.0;

        $bankService = app(HrmStandardBankFormatService::class);

        foreach ($run->items as $item) {
            $emp = $item->employee;
            $profile = $emp?->profile;
            $bd = is_array($item->breakdown) ? $item->breakdown : [];

            $gross = (float) $item->gross;
            $net = (float) $item->net;
            $ded = (float) $item->deductions;

            $currentGross += $gross;
            $currentNet += $net;
            $currentDeductions += $ded;
            $currentCount++;

            if (! $emp) {
                continue;
            }

            $empName = trim("{$emp->first_name} {$emp->last_name}");
            $empCode = $emp->employee_code ?: (string) $emp->id;

            // 1. Insurance Discrepancy Check (فاقد شماره بیمه یا کد ملی نامعتبر)
            $nid = trim((string) ($profile?->national_id ?? $emp->national_id ?? ''));
            $custom = is_array($profile?->custom_fields) ? $profile->custom_fields : [];
            $insNo = trim((string) ($profile?->insurance_number ?: ($custom['insurance_no'] ?? $custom['ssn'] ?? $emp->insurance_no ?? '')));

            $insErrors = [];
            if ($nid === '' || ! \Modules\Hrm\Support\IranianNationalId::isValid($nid)) {
                $insErrors[] = "کد ملی نامعتبر یا خالی است ({$nid})";
            }
            if ($insNo === '') {
                $insErrors[] = "شماره بیمه تأمین اجتماعی ثبت نشده است";
            }

            if (! empty($insErrors)) {
                $insuranceDiscrepancies[] = [
                    'item_id' => $item->id,
                    'employee_id' => $emp->id,
                    'employee_code' => $empCode,
                    'employee_name' => $empName,
                    'national_id' => $nid,
                    'insurance_no' => $insNo,
                    'gross' => $gross,
                    'errors' => $insErrors,
                ];
            }

            // 2. Banking Discrepancy Check (شبا نامعتبر یا خالی یا مبلغ خالص غیرمثبت)
            $sheba = trim((string) ($emp->sheba_number ?: ($profile?->sheba ?? $emp->bank_sheba ?? '')));
            $bankErrors = [];
            if ($sheba === '') {
                $bankErrors[] = "شماره شبای بانکی خالی است";
            } elseif (! $bankService->validateShebaMod97($sheba)) {
                $bankErrors[] = "شماره شبا نامعتبر است (عدم تطابق با الگوریتم Mod-97)";
            }
            if ($net <= 0) {
                $bankErrors[] = "مبلغ خالص پرداختی غیرمثبت است (" . number_format($net) . " ریال)";
            }

            if (! empty($bankErrors)) {
                $bankingDiscrepancies[] = [
                    'item_id' => $item->id,
                    'employee_id' => $emp->id,
                    'employee_code' => $empCode,
                    'employee_name' => $empName,
                    'sheba_number' => $sheba,
                    'net' => $net,
                    'errors' => $bankErrors,
                ];
            }

            // 3. Abnormal Overtime and Shift Allowance Check
            $overtimePay = (float) ($bd['overtime'] ?? 0);
            $baseSalary = max(1.0, (float) ($bd['base'] ?? $emp->base_salary ?? 1.0));
            $hourlyWage = $baseSalary / 220.0;
            $overtimeHours = $hourlyWage > 0 ? round($overtimePay / ($hourlyWage * 1.4), 1) : 0.0;
            $shiftAllowance = (float) ($bd['shift_allowance'] ?? 0);
            $shiftPercent = $baseSalary > 0 ? round(($shiftAllowance / $baseSalary) * 100, 1) : 0.0;

            if ($overtimeHours > $overtimeThreshold || $shiftPercent > $shiftThreshold || $overtimePay > $baseSalary) {
                $abnormalAttendance[] = [
                    'item_id' => $item->id,
                    'employee_id' => $emp->id,
                    'employee_code' => $empCode,
                    'employee_name' => $empName,
                    'base_salary' => $baseSalary,
                    'overtime_pay' => $overtimePay,
                    'overtime_hours_estimated' => $overtimeHours,
                    'shift_allowance' => $shiftAllowance,
                    'shift_percent' => $shiftPercent,
                    'flags' => array_filter([
                        $overtimeHours > $overtimeThreshold ? "اضافه‌کاری غیرعادی (> {$overtimeThreshold} ساعت)" : null,
                        $shiftPercent > $shiftThreshold ? "درصد نوبت‌کاری فراتر از سقف قانونی ۲۲.۵٪" : null,
                        $overtimePay > $baseSalary ? "مبلغ اضافه‌کاری بیش از ۱۰۰٪ حقوق پایه است" : null,
                    ]),
                ];
            }

            // 4. Retroactive adjustments impact on current period
            $retroCurrent = (float) ($bd['retro_current_year'] ?? 0);
            $retroPrior = (float) ($bd['retro_prior_years'] ?? 0);
            $retroTotal = (float) ($bd['retro_total'] ?? ($retroCurrent + $retroPrior));

            if ($retroTotal > 0) {
                $totalRetroAmount += $retroTotal;
                $retroItems[] = [
                    'item_id' => $item->id,
                    'employee_id' => $emp->id,
                    'employee_code' => $empCode,
                    'employee_name' => $empName,
                    'retro_current_year' => $retroCurrent,
                    'retro_prior_years' => $retroPrior,
                    'total_retro' => $retroTotal,
                    'gross_with_retro' => $gross,
                    'retro_share_percent' => $gross > 0 ? round(($retroTotal / $gross) * 100, 1) : 0.0,
                ];
            }
        }

        // 5. Comparison with Previous Payroll Period
        $prevYear = (int) $run->year;
        $prevMonth = (int) $run->month - 1;
        if ($prevMonth < 1) {
            $prevMonth = 12;
            $prevYear--;
        }

        $prevRun = HrmPayrollRun::query()
            ->where('id', '!=', $run->id)
            ->where('year', $prevYear)
            ->where('month', $prevMonth)
            ->when($run->subsidiary_company_id && Schema::hasColumn('hrm_payroll_runs', 'subsidiary_company_id'), function ($q) use ($run) {
                $q->where('subsidiary_company_id', $run->subsidiary_company_id);
            })
            ->latest('id')
            ->first();

        $prevGross = 0.0;
        $prevDeductions = 0.0;
        $prevNet = 0.0;
        $prevCount = 0;

        if ($prevRun) {
            $prevItems = HrmPayrollItem::query()->where('payroll_run_id', $prevRun->id)->get();
            $prevGross = (float) $prevItems->sum('gross');
            $prevDeductions = (float) $prevItems->sum('deductions');
            $prevNet = (float) $prevItems->sum('net');
            $prevCount = $prevItems->count();
        }

        $diffGross = round($currentGross - $prevGross, 2);
        $diffDeductions = round($currentDeductions - $prevDeductions, 2);
        $diffNet = round($currentNet - $prevNet, 2);
        $diffCount = $currentCount - $prevCount;

        $pctChangeGross = $prevGross > 0 ? round(($diffGross / $prevGross) * 100, 2) : 0.0;
        $pctChangeNet = $prevNet > 0 ? round(($diffNet / $prevNet) * 100, 2) : 0.0;

        $isReady = empty($insuranceDiscrepancies) && empty($bankingDiscrepancies);

        return [
            'run_id' => $run->id,
            'period' => "{$run->year}/" . sprintf('%02d', $run->month),
            'is_ready_for_approval' => $isReady,
            'insurance_discrepancies' => [
                'count' => count($insuranceDiscrepancies),
                'items' => $insuranceDiscrepancies,
            ],
            'banking_discrepancies' => [
                'count' => count($bankingDiscrepancies),
                'items' => $bankingDiscrepancies,
            ],
            'abnormal_attendance' => [
                'count' => count($abnormalAttendance),
                'items' => $abnormalAttendance,
            ],
            'retro_adjustments' => [
                'count' => count($retroItems),
                'total_amount' => round($totalRetroAmount, 2),
                'items' => $retroItems,
            ],
            'period_comparison' => [
                'current' => [
                    'period' => "{$run->year}/" . sprintf('%02d', $run->month),
                    'gross' => round($currentGross, 2),
                    'deductions' => round($currentDeductions, 2),
                    'net' => round($currentNet, 2),
                    'employee_count' => $currentCount,
                ],
                'previous' => [
                    'period' => "{$prevYear}/" . sprintf('%02d', $prevMonth),
                    'gross' => round($prevGross, 2),
                    'deductions' => round($prevDeductions, 2),
                    'net' => round($prevNet, 2),
                    'employee_count' => $prevCount,
                ],
                'difference' => [
                    'gross' => $diffGross,
                    'deductions' => $diffDeductions,
                    'net' => $diffNet,
                    'employee_count' => $diffCount,
                ],
                'percentage_change' => [
                    'gross' => $pctChangeGross,
                    'net' => $pctChangeNet,
                ],
            ],
        ];
    }
}

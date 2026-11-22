<?php

namespace Modules\Hrm\Services;

use Carbon\Carbon;
use InvalidArgumentException;
use Modules\Accounting\Entities\AccChartAccount;
use Modules\Accounting\Entities\AccFiscalYear;
use Modules\Accounting\Entities\AccJournalEntry;
use Modules\Accounting\Entities\AccJournalLine;
use Modules\Hrm\Entities\HrmPayrollItem;
use Modules\Hrm\Entities\HrmPayrollJournalLink;
use Modules\Hrm\Entities\HrmPayrollRetroAdjustment;
use Modules\Hrm\Entities\HrmPayrollRun;
use Modules\Hrm\Entities\HrmPayrollSetting;

class HrmPayrollAccountingIntegrationService
{
    /**
     * Resolve account by chart code or configured setting key.
     * Creates standard postable account node if missing to ensure balanced ledger entry.
     */
    private function resolveAccount(string $settingKey, string $defaultCode, string $name, string $type): int
    {
        if (! class_exists(AccChartAccount::class) || ! \Illuminate\Support\Facades\Schema::hasTable('acc_chart_accounts')) {
            return 1;
        }

        $code = $defaultCode;
        if (\Illuminate\Support\Facades\Schema::hasTable('hrm_payroll_settings')) {
            $setting = HrmPayrollSetting::query()->where('key', $settingKey)->first();
            if ($setting && ! empty($setting->value)) {
                $code = (string) $setting->value;
            }
        }

        $account = AccChartAccount::query()->where('code', $code)->first();
        if (! $account) {
            $account = AccChartAccount::query()->create([
                'code' => $code,
                'name' => $name,
                'type' => $type,
                'is_postable' => true,
            ]);
        }

        return (int) $account->id;
    }

    /**
     * Issue balanced, segregated Rahkaran-standard accounting vouchers:
     * - Segregates:
     *   1) Current period wages expense (هزینه حقوق و دستمزد جاری)
     *   2) Current fiscal year retro pay expense (هزینه معوقات احکام سال جاری)
     *   3) Prior fiscal years retro adjustment (تعدیلات سنواتی - معوقات سنوات قبل)
     *   4) Employer social security & unemployment insurance (۲۳٪ سهم کارفرما)
     * - Credits:
     *   1) Social Security Organization payable (بیمه پرداختنی تأمین اجتماعی ۳۰٪)
     *   2) Statutory Salary Tax payable (مالیات تکلیفی حقوق پرداختنی)
     *   3) Net Salaries payable to staff (حقوق پرداختنی خالص پرسنل)
     * - Enforces absolute debit = credit balance validation.
     *
     * @return array{
     *   journal_entry_id: int|null,
     *   document_no: string,
     *   total_debit: float,
     *   total_credit: float,
     *   is_balanced: bool,
     *   voucher_segments: array<string, mixed>
     * }
     */
    public function issuePayrollVouchers(HrmPayrollRun $run, ?int $userId = null): array
    {
        $run->loadMissing('items');

        $totalGross = (float) $run->items->sum('gross');
        $totalNet = (float) $run->items->sum('net');
        $totalTax = (float) $run->items->sum('tax');
        $totalEmployeeInsurance = (float) $run->items->sum('employee_insurance');
        $totalEmployerInsurance = (float) $run->items->sum('employer_insurance');

        // Segregate retroactive salary differentials
        $currentFiscalYear = (int) ($run->year ?: now()->format('Y'));
        $retroItems = HrmPayrollRetroAdjustment::query()
            ->where('payroll_run_id', $run->id)
            ->get();

        $retroCurrentYear = 0.0;
        $retroPriorYears = 0.0;

        foreach ($retroItems as $r) {
            $amt = (float) $r->differential_amount;
            if ((int) $r->target_year === $currentFiscalYear) {
                $retroCurrentYear += $amt;
            } else {
                $retroPriorYears += $amt;
            }
        }

        $baseCurrentGross = max(0.0, $totalGross - $retroCurrentYear - $retroPriorYears);

        // Chart Account IDs
        $accSalaryExpense = $this->resolveAccount('acc_code_salary_expense', '510101', 'هزینه حقوق و دستمزد مستقیم جاری', 'expense');
        $accRetroCurrentExpense = $this->resolveAccount('acc_code_retro_current', '510102', 'هزینه معوقات احکام سال جاری', 'expense');
        $accRetroPriorAdjustment = $this->resolveAccount('acc_code_retro_prior', '310201', 'تعدیلات سنواتی - معوقات احکام سنوات قبل', 'equity');
        $accEmployerInsExpense = $this->resolveAccount('acc_code_employer_ins', '510103', 'هزینه بیمه سهم کارفرما و بیکاری', 'expense');

        $accSsoPayable = $this->resolveAccount('acc_code_sso_payable', '210201', 'سایر حساب‌ها و اسناد پرداختنی - سازمان تأمین اجتماعی', 'liability');
        $accTaxPayable = $this->resolveAccount('acc_code_tax_payable', '210202', 'مالیات تکلیفی حقوق پرداختنی', 'liability');
        $accNetSalaryPayable = $this->resolveAccount('acc_code_net_payable', '210301', 'حقوق و دستمزد پرداختنی به پرسنل', 'liability');

        $documentNo = app(HrmSerialService::class)->next('payroll_run');
        $journalEntryId = null;

        $totalDebit = round($baseCurrentGross + $retroCurrentYear + $retroPriorYears + $totalEmployerInsurance, 2);
        $totalCredit = round(($totalEmployeeInsurance + $totalEmployerInsurance) + $totalTax + $totalNet, 2);

        // Mandatory ledger balance assertion
        if (abs($totalDebit - $totalCredit) > 1.0) {
            throw new InvalidArgumentException("عدم تراز سند حسابداری حقوق: جمع بدهکار ({$totalDebit}) با جمع بستانکار ({$totalCredit}) برابر نیست.");
        }

        if (class_exists(AccJournalEntry::class) && \Illuminate\Support\Facades\Schema::hasTable('acc_journal_entries')) {
            $fiscalYearId = null;
            if (class_exists(AccFiscalYear::class) && \Illuminate\Support\Facades\Schema::hasTable('acc_fiscal_years')) {
                $fy = AccFiscalYear::query()->where('is_closed', false)->orderByDesc('id')->first();
                $fiscalYearId = $fy?->id;
            }

            $entry = AccJournalEntry::query()->create([
                'fiscal_year_id' => $fiscalYearId,
                'document_no' => $documentNo,
                'document_date' => now()->toDateString(),
                'description' => "سند شناسایی و تفکیک هزینه حقوق، معوقات و بیمه دوره {$run->month}/{$run->year} ({$run->title})",
                'status' => 'draft',
                'created_by' => $userId,
            ]);

            $journalEntryId = $entry->id;

            // Debit 1: Current period wages expense
            AccJournalLine::query()->create([
                'journal_entry_id' => $entry->id,
                'account_id' => $accSalaryExpense,
                'debit' => $baseCurrentGross,
                'credit' => 0,
                'description' => 'هزینه حقوق و دستمزد پرسنل - پرداخت جاری',
            ]);

            // Debit 2: Current year retro expense (if any)
            if ($retroCurrentYear > 0) {
                AccJournalLine::query()->create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $accRetroCurrentExpense,
                    'debit' => $retroCurrentYear,
                    'credit' => 0,
                    'description' => 'هزینه معوقات احکام کارگزینی سال جاری',
                ]);
            }

            // Debit 3: Prior years retro adjustment (تعدیلات سنواتی سنوات قبل)
            if ($retroPriorYears > 0) {
                AccJournalLine::query()->create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $accRetroPriorAdjustment,
                    'debit' => $retroPriorYears,
                    'credit' => 0,
                    'description' => 'تعدیلات سنواتی - معوقات احکام سال‌های مالی قبل',
                ]);
            }

            // Debit 4: Employer insurance expense (20% + 3%)
            if ($totalEmployerInsurance > 0) {
                AccJournalLine::query()->create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $accEmployerInsExpense,
                    'debit' => $totalEmployerInsurance,
                    'credit' => 0,
                    'description' => 'هزینه بیمه سهم کارفرما و بیمه بیکاری (۲۳٪)',
                ]);
            }

            // Credit 1: SSO Insurance payable (Worker 7% + Employer 23% = 30%)
            $totalSsoPayable = $totalEmployeeInsurance + $totalEmployerInsurance;
            AccJournalLine::query()->create([
                'journal_entry_id' => $entry->id,
                'account_id' => $accSsoPayable,
                'debit' => 0,
                'credit' => $totalSsoPayable,
                'description' => 'حق بیمه پرداختنی سازمان تأمین اجتماعی (۳۰٪)',
            ]);

            // Credit 2: Salary Tax Payable (مالیات تکلیفی حقوق پرداختنی)
            AccJournalLine::query()->create([
                'journal_entry_id' => $entry->id,
                'account_id' => $accTaxPayable,
                'debit' => 0,
                'credit' => $totalTax,
                'description' => 'مالیات تکلیفی حقوق پرداختنی سازمان امور مالیاتی',
            ]);

            // Credit 3: Net Salaries Payable to staff (حقوق پرداختنی خالص)
            AccJournalLine::query()->create([
                'journal_entry_id' => $entry->id,
                'account_id' => $accNetSalaryPayable,
                'debit' => 0,
                'credit' => $totalNet,
                'description' => 'حقوق پرداختنی خالص به پرسنل جهت حواله بانکی',
            ]);
        }

        // Record tracking link
        HrmPayrollJournalLink::query()->create([
            'payroll_run_id' => $run->id,
            'journal_entry_id' => $journalEntryId,
            'voucher_segment' => 'comprehensive_payroll_voucher',
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'line_count' => 7,
            'status' => 'draft',
            'payload_snapshot' => [
                'current_gross' => $baseCurrentGross,
                'retro_current_year' => $retroCurrentYear,
                'retro_prior_years' => $retroPriorYears,
                'employer_insurance' => $totalEmployerInsurance,
                'sso_payable' => $totalEmployeeInsurance + $totalEmployerInsurance,
                'tax_payable' => $totalTax,
                'net_payable' => $totalNet,
            ],
        ]);

        return [
            'journal_entry_id' => $journalEntryId,
            'document_no' => $documentNo,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'is_balanced' => true,
            'voucher_segments' => [
                'current_wages_expense' => $baseCurrentGross,
                'retro_current_year_expense' => $retroCurrentYear,
                'retro_prior_years_adjustment' => $retroPriorYears,
                'employer_insurance_expense' => $totalEmployerInsurance,
                'sso_payable' => $totalEmployeeInsurance + $totalEmployerInsurance,
                'tax_payable' => $totalTax,
                'net_payable' => $totalNet,
            ],
        ];
    }
}

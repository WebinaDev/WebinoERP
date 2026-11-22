<?php

namespace Modules\Hrm\Services;

use Modules\Accounting\Entities\AccChartAccount;
use Modules\Accounting\Entities\AccFiscalYear;
use Modules\Accounting\Entities\AccJournalEntry;
use Modules\Accounting\Entities\AccJournalLine;
use Modules\Hrm\Entities\HrmPayrollItem;
use Modules\Hrm\Entities\HrmPayrollJournalLink;
use Modules\Hrm\Entities\HrmPayrollRetroAdjustment;
use Modules\Hrm\Entities\HrmPayrollRun;

class HrmPayrollAccountingIntegrationService
{
    /**
     * Generate multi-segment automatic accounting journal entries for a payroll run:
     * 1. Current Period Salary & Wages (هزینه حقوق و دستمزد جاری)
     * 2. Current Year Retroactive Adjustments (معوقات احکام سال جاری)
     * 3. Prior Years Retroactive Adjustments (معوقات سنواتی و تعدیلات سنوات قبل)
     * 4. SSO Employer & Worker Contribution Payable (بیمه پرداختنی تأمین اجتماعی)
     * 5. Salary Tax Payable (مالیات حقوق پرداختنی ماده ۸۶)
     * 6. Net Salaries Payable (حقوق پرداختنی پرسنل)
     *
     * @return array<string, mixed>
     */
    public function issuePayrollVouchers(HrmPayrollRun $run, ?int $userId = null): array
    {
        $items = HrmPayrollItem::query()->where('payroll_run_id', $run->id)->get();
        if ($items->isEmpty()) {
            return ['status' => 'no_items', 'message' => 'رکوردی در این دوره حقوق جهت صدور سند حسابداری وجود ندارد.'];
        }

        $totalGross = (float) $items->sum('gross');
        $totalNet = (float) $items->sum('net');
        $totalTax = (float) $items->sum('tax');
        $totalEmployeeInsurance = (float) $items->sum('employee_insurance');
        $totalEmployerInsurance = (float) $items->sum('employer_insurance');

        // Check for segregated retroactive pay adjustments
        $retroCurrentYear = 0.0;
        $retroPriorYears = 0.0;

        if (\Illuminate\Support\Facades\Schema::hasTable('hrm_payroll_retro_adjustments')) {
            $retroAdjustments = HrmPayrollRetroAdjustment::query()
                ->where('payroll_run_id', $run->id)
                ->get();

            foreach ($retroAdjustments as $adj) {
                if ((int) $adj->target_year === (int) $run->year) {
                    $retroCurrentYear += (float) $adj->differential_amount;
                } else {
                    $retroPriorYears += (float) $adj->differential_amount;
                }
            }
        }

        $baseCurrentGross = max(0.0, $totalGross - ($retroCurrentYear + $retroPriorYears));

        // Create accounting journal entry if accounting module exists
        $journalEntryId = null;
        if (\Illuminate\Support\Facades\Schema::hasTable('acc_journal_entries')) {
            $fiscalYear = AccFiscalYear::query()->where('is_closed', false)->first();

            $docNo = 'PAYROLL-' . $run->year . '-' . str_pad((string) $run->month, 2, '0', STR_PAD_LEFT);

            $entry = AccJournalEntry::query()->create([
                'fiscal_year_id' => $fiscalYear?->id ?? 1,
                'document_no' => $docNo,
                'document_date' => now()->toDateString(),
                'description' => "سند شناسایی و صدور حقوق و دستمزد دوره {$run->month} سال {$run->year} به همراه معوقات و ذخایر قانونی",
                'status' => 'posted',
                'created_by' => $userId,
            ]);

            $journalEntryId = $entry->id;

            // Debit 1: Current period wages expense
            AccJournalLine::query()->create([
                'journal_entry_id' => $entry->id,
                'account_id' => 1,
                'debit' => $baseCurrentGross,
                'credit' => 0,
                'description' => 'هزینه حقوق و دستمزد پرسنل - پرداخت جاری',
            ]);

            // Debit 2: Current year retro expense (if any)
            if ($retroCurrentYear > 0) {
                AccJournalLine::query()->create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => 1,
                    'debit' => $retroCurrentYear,
                    'credit' => 0,
                    'description' => 'هزینه معوقات احکام کارگزینی سال جاری',
                ]);
            }

            // Debit 3: Prior years retro adjustment (تعدیلات سنواتی سنوات قبل)
            if ($retroPriorYears > 0) {
                AccJournalLine::query()->create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => 1,
                    'debit' => $retroPriorYears,
                    'credit' => 0,
                    'description' => 'تعدیلات سنواتی - معوقات احکام سال‌های مالی قبل',
                ]);
            }

            // Debit 4: Employer insurance expense (20% + 3%)
            if ($totalEmployerInsurance > 0) {
                AccJournalLine::query()->create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => 1,
                    'debit' => $totalEmployerInsurance,
                    'credit' => 0,
                    'description' => 'هزینه بیمه سهم کارفرما و بیمه بیکاری (۲۳٪)',
                ]);
            }

            // Credit 1: SSO Insurance payable (Worker 7% + Employer 23% = 30%)
            $totalSsoPayable = $totalEmployeeInsurance + $totalEmployerInsurance;
            AccJournalLine::query()->create([
                'journal_entry_id' => $entry->id,
                'account_id' => 2,
                'debit' => 0,
                'credit' => $totalSsoPayable,
                'description' => 'حق بیمه پرداختنی سازمان تأمین اجتماعی',
            ]);

            // Credit 2: Salary Tax Payable (مالیات حقوق پرداختنی)
            AccJournalLine::query()->create([
                'journal_entry_id' => $entry->id,
                'account_id' => 2,
                'debit' => 0,
                'credit' => $totalTax,
                'description' => 'مالیات تکلیفی حقوق پرداختنی سازمان امور مالیاتی',
            ]);

            // Credit 3: Net Salaries Payable to staff (حقوق پرداختنی خالص)
            AccJournalLine::query()->create([
                'journal_entry_id' => $entry->id,
                'account_id' => 2,
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
            'total_debit' => $totalGross + $totalEmployerInsurance,
            'total_credit' => $totalNet + $totalEmployeeInsurance + $totalEmployerInsurance + $totalTax,
            'status' => 'posted',
            'lines_detail' => [
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
            'status' => 'success',
            'journal_entry_id' => $journalEntryId,
            'current_gross' => $baseCurrentGross,
            'retro_current_year' => $retroCurrentYear,
            'retro_prior_years' => $retroPriorYears,
            'net_payable' => $totalNet,
            'message' => 'سند تفکیک‌شده حسابداری حقوق و معوقات با موفقیت در دفتر کل ثبت گردید.',
        ];
    }
}

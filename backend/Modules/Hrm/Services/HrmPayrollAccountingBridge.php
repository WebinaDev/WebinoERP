<?php

namespace Modules\Hrm\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Hrm\Entities\HrmPayrollRetroAdjustment;
use Modules\Hrm\Entities\HrmPayrollRun;

/**
 * On payroll approve/paid: create AccJournalEntry with full segregated accounting lines:
 * - Current payroll gross wage expense (هزینه حقوق جاری)
 * - Current year retroactive salary differential (معوقات سال جاری)
 * - Prior years retroactive adjustment (معوقات سنوات قبل)
 * - Employer social security & unemployment insurance (بیمه سهم کارفرما)
 * - Employee insurance payable (بیمه سهم کارگر - بستانکار)
 * - Tax payable (مالیات پرداختنی)
 * - Loan & advance deductions (کسورات وام)
 * - Net salary payable (حقوق پرداختنی - خالص)
 */
class HrmPayrollAccountingBridge
{
    /**
     * @return array{ok: bool, journal_entry_id: int|null, message: string}
     */
    public function postPayrollRun(HrmPayrollRun $run, ?int $userId = null): array
    {
        $settings = app(IranianPayrollCalculator::class)->settings();
        if (! ($settings['payroll_accounting_enabled'] ?? true)) {
            return ['ok' => false, 'journal_entry_id' => null, 'message' => 'payroll_accounting_enabled=false'];
        }
        if (! class_exists(\Modules\Accounting\Entities\AccJournalEntry::class)
            || ! Schema::hasTable('acc_journal_entries')
            || ! Schema::hasTable('acc_journal_lines')
            || ! Schema::hasTable('acc_chart_accounts')) {
            return ['ok' => false, 'journal_entry_id' => null, 'message' => __('messages.accounting_module_tables_unavailable')];
        }
        if ($run->journal_entry_id) {
            return ['ok' => true, 'journal_entry_id' => (int) $run->journal_entry_id, 'message' => 'already_linked'];
        }

        $run->loadMissing('items');
        $gross = round((float) $run->items->sum('gross'), 2);
        $net = round((float) $run->items->sum('net'), 2);
        $employeeIns = round((float) $run->items->sum('employee_insurance'), 2);
        $employerIns = round((float) $run->items->sum('employer_insurance'), 2);
        $tax = round((float) $run->items->sum('tax'), 2);
        $deductions = round((float) $run->items->sum('deductions'), 2);
        $loanDeductions = max(0.0, round($deductions - $employeeIns - $tax, 2));

        if ($gross <= 0) {
            return ['ok' => false, 'journal_entry_id' => null, 'message' => 'empty_run'];
        }

        // Segregate retroactive salary differentials
        $currentYear = (int) ($run->year ?: now()->format('Y'));
        $retroCurrentYear = 0.0;
        $retroPriorYears = 0.0;

        if (Schema::hasTable('hrm_payroll_retro_adjustments')) {
            $retroItems = HrmPayrollRetroAdjustment::query()->where('payroll_run_id', $run->id)->get();
            foreach ($retroItems as $r) {
                $amt = (float) $r->differential_amount;
                if ((int) $r->target_year === $currentYear) {
                    $retroCurrentYear += $amt;
                } else {
                    $retroPriorYears += $amt;
                }
            }
        }

        $baseGross = max(0.0, round($gross - $retroCurrentYear - $retroPriorYears, 2));

        $expenseCode = (string) ($settings['payroll_expense_account_code'] ?? '510101');
        $retroCode = (string) ($settings['acc_code_retro_current'] ?? '510102');
        $retroPriorCode = (string) ($settings['acc_code_retro_prior'] ?? '310201');
        $employerInsCode = (string) ($settings['acc_code_employer_ins'] ?? '510103');
        $employeeSsoPayableCode = (string) ($settings['acc_code_employee_sso_payable'] ?? '210204');
        $ssoPayableCode = (string) ($settings['acc_code_sso_payable'] ?? '210201');
        $taxPayableCode = (string) ($settings['acc_code_tax_payable'] ?? '210202');
        $loanPayableCode = (string) ($settings['acc_code_loan_deductions'] ?? '210203');
        $netPayableCode = (string) ($settings['payroll_payable_account_code'] ?? '210301');

        $missingAccounts = [];

        $accExpense = $this->findAccount($expenseCode);
        if (! $accExpense) {
            $missingAccounts[] = "حساب هزینه حقوق جاری (کد: {$expenseCode})";
        }

        $accNetPayable = $this->findAccount($netPayableCode);
        if (! $accNetPayable) {
            $missingAccounts[] = "حساب حقوق پرداختنی خالص (کد: {$netPayableCode})";
        }

        $accRetro = null;
        if ($retroCurrentYear > 0) {
            $accRetro = $this->findAccount($retroCode);
            if (! $accRetro) {
                $missingAccounts[] = "حساب معوقات سال جاری (کد: {$retroCode})";
            }
        }

        $accRetroPrior = null;
        if ($retroPriorYears > 0) {
            $accRetroPrior = $this->findAccount($retroPriorCode);
            if (! $accRetroPrior) {
                $missingAccounts[] = "حساب معوقات سنوات قبل (کد: {$retroPriorCode})";
            }
        }

        $accEmployerIns = null;
        if ($employerIns > 0) {
            $accEmployerIns = $this->findAccount($employerInsCode);
            if (! $accEmployerIns) {
                $missingAccounts[] = "حساب هزینه بیمه سهم کارفرما (کد: {$employerInsCode})";
            }
        }

        $accEmployeeSso = null;
        if ($employeeIns > 0) {
            $accEmployeeSso = $this->findAccount($employeeSsoPayableCode) ?: $this->findAccount($ssoPayableCode);
            if (! $accEmployeeSso) {
                $missingAccounts[] = "حساب بیمه سهم کارگر پرداختنی (کد: {$employeeSsoPayableCode} یا {$ssoPayableCode})";
            }
        }

        $accTaxPayable = null;
        if ($tax > 0) {
            $accTaxPayable = $this->findAccount($taxPayableCode);
            if (! $accTaxPayable) {
                $missingAccounts[] = "حساب مالیات حقوق پرداختنی (کد: {$taxPayableCode})";
            }
        }

        $accLoanPayable = null;
        if ($loanDeductions > 0) {
            $accLoanPayable = $this->findAccount($loanPayableCode);
            if (! $accLoanPayable) {
                $missingAccounts[] = "حساب کسورات وام و مساعده (کد: {$loanPayableCode})";
            }
        }

        if (! empty($missingAccounts)) {
            $errMessage = 'حساب‌های معین مورد نیاز در جدول کدینگ (Chart of Accounts) تعریف نشده‌اند: ' . implode('، ', $missingAccounts);
            Log::error("HrmPayrollAccountingBridge: {$errMessage}");
            return ['ok' => false, 'journal_entry_id' => null, 'message' => $errMessage];
        }

        $fiscalYearId = null;
        if (Schema::hasTable('acc_fiscal_years') && class_exists(\Modules\Accounting\Entities\AccFiscalYear::class)) {
            $fy = \Modules\Accounting\Entities\AccFiscalYear::query()->orderByDesc('id')->first();
            $fiscalYearId = $fy?->id;
        }

        try {
            $jeId = DB::transaction(function () use (
                $run, $accExpense, $accRetro, $accRetroPrior, $accEmployerIns,
                $accEmployeeSso, $accTaxPayable, $accLoanPayable, $accNetPayable,
                $baseGross, $retroCurrentYear, $retroPriorYears, $employerIns,
                $employeeIns, $tax, $loanDeductions, $net, $userId, $fiscalYearId
            ) {
                $serial = app(HrmSerialService::class)->next('payroll_run');
                $je = \Modules\Accounting\Entities\AccJournalEntry::query()->create([
                    'fiscal_year_id' => $fiscalYearId,
                    'document_no' => $serial,
                    'document_date' => now()->toDateString(),
                    'description' => 'حقوق و دستمزد و تفکیک معوقات و بیمه — '.$run->title.' (#'.$run->id.')',
                    'status' => 'posted',
                    'created_by' => $userId,
                ]);

                // 1. Debits (بدهکار):
                // 1.1 هزینه حقوق جاری
                if ($baseGross > 0) {
                    \Modules\Accounting\Entities\AccJournalLine::query()->create([
                        'journal_entry_id' => $je->id,
                        'account_id' => $accExpense->id,
                        'debit' => $baseGross,
                        'credit' => 0,
                        'description' => 'هزینه حقوق و دستمزد جاری',
                    ]);
                }

                // 1.2 معوقات سال جاری
                if ($retroCurrentYear > 0 && $accRetro) {
                    \Modules\Accounting\Entities\AccJournalLine::query()->create([
                        'journal_entry_id' => $je->id,
                        'account_id' => $accRetro->id,
                        'debit' => $retroCurrentYear,
                        'credit' => 0,
                        'description' => 'هزینه معوقات احکام سال جاری',
                    ]);
                }

                // 1.3 معوقات سنوات قبل
                if ($retroPriorYears > 0 && $accRetroPrior) {
                    \Modules\Accounting\Entities\AccJournalLine::query()->create([
                        'journal_entry_id' => $je->id,
                        'account_id' => $accRetroPrior->id,
                        'debit' => $retroPriorYears,
                        'credit' => 0,
                        'description' => 'تعدیلات سنواتی - معوقات احکام سنوات قبل',
                    ]);
                }

                // 1.4 بیمه سهم کارفرما (هزینه بدهکار)
                if ($employerIns > 0 && $accEmployerIns) {
                    \Modules\Accounting\Entities\AccJournalLine::query()->create([
                        'journal_entry_id' => $je->id,
                        'account_id' => $accEmployerIns->id,
                        'debit' => $employerIns,
                        'credit' => 0,
                        'description' => 'هزینه بیمه سهم کارفرما و بیکاری (۲۳٪)',
                    ]);
                }

                // 2. Credits (بستانکار):
                // 2.1 بیمه سهم کارفرما پرداختنی
                if ($employerIns > 0 && $accEmployerIns) {
                    $ssoAcc = $this->findAccount('210201') ?: $accEmployeeSso;
                    \Modules\Accounting\Entities\AccJournalLine::query()->create([
                        'journal_entry_id' => $je->id,
                        'account_id' => $ssoAcc->id,
                        'debit' => 0,
                        'credit' => $employerIns,
                        'description' => 'بیمه سهم کارفرما پرداختنی تأمین اجتماعی',
                    ]);
                }

                // 2.2 بیمه سهم کارگر پرداختنی
                if ($employeeIns > 0 && $accEmployeeSso) {
                    \Modules\Accounting\Entities\AccJournalLine::query()->create([
                        'journal_entry_id' => $je->id,
                        'account_id' => $accEmployeeSso->id,
                        'debit' => 0,
                        'credit' => $employeeIns,
                        'description' => 'حق بیمه سهم کارگر پرداختنی (۷٪)',
                    ]);
                }

                // 2.3 مالیات پرداختنی
                if ($tax > 0 && $accTaxPayable) {
                    \Modules\Accounting\Entities\AccJournalLine::query()->create([
                        'journal_entry_id' => $je->id,
                        'account_id' => $accTaxPayable->id,
                        'debit' => 0,
                        'credit' => $tax,
                        'description' => 'مالیات تکلیفی حقوق پرداختنی',
                    ]);
                }

                // 2.4 کسورات وام
                if ($loanDeductions > 0 && $accLoanPayable) {
                    \Modules\Accounting\Entities\AccJournalLine::query()->create([
                        'journal_entry_id' => $je->id,
                        'account_id' => $accLoanPayable->id,
                        'debit' => 0,
                        'credit' => $loanDeductions,
                        'description' => 'کسور اقساط وام و مساعده پرسنل',
                    ]);
                }

                // 2.5 حقوق پرداختنی (خالص)
                \Modules\Accounting\Entities\AccJournalLine::query()->create([
                    'journal_entry_id' => $je->id,
                    'account_id' => $accNetPayable->id,
                    'debit' => 0,
                    'credit' => $net,
                    'description' => 'خالص حقوق پرداختنی پرسنل',
                ]);

                $payload = ['journal_entry_id' => $je->id, 'paid_at' => now()];
                if (Schema::hasColumn('hrm_payroll_runs', 'serial_no')) {
                    $payload['serial_no'] = $serial;
                }
                $run->update($payload);

                return (int) $je->id;
            });

            return ['ok' => true, 'journal_entry_id' => $jeId, 'message' => 'posted'];
        } catch (\Throwable $e) {
            Log::warning('HrmPayrollAccountingBridge failed: '.$e->getMessage());

            return ['ok' => false, 'journal_entry_id' => null, 'message' => $e->getMessage()];
        }
    }

    private function findAccount(string $code): ?object
    {
        $q = \Modules\Accounting\Entities\AccChartAccount::query()->where('code', $code);
        if (Schema::hasColumn('acc_chart_accounts', 'is_postable')) {
            $postable = (clone $q)->where('is_postable', true)->first();
            if ($postable) {
                return $postable;
            }
        }

        return $q->first();
    }
}

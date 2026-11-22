<?php

namespace Modules\Hrm\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Hrm\Entities\HrmPayrollRetroAdjustment;
use Modules\Hrm\Entities\HrmPayrollRun;

/**
 * On payroll approve/paid: create AccJournalEntry with full segregated accounting lines:
 * - Gross wages expense
 * - Employer social security & unemployment insurance (23%)
 * - Retroactive pay expense (current year vs prior years)
 * - Worker insurance payable (7%)
 * - Tax payable
 * - Loan & advance deductions payable
 * - Net salary payable
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
        $otherDeductions = max(0.0, round($deductions - $employeeIns - $tax, 2));

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
        $ssoPayableCode = (string) ($settings['acc_code_sso_payable'] ?? '210201');
        $taxPayableCode = (string) ($settings['acc_code_tax_payable'] ?? '210202');
        $loanPayableCode = (string) ($settings['acc_code_loan_deductions'] ?? '210203');
        $netPayableCode = (string) ($settings['payroll_payable_account_code'] ?? '210301');

        $accExpense = $this->findAccount($expenseCode);
        $accNetPayable = $this->findAccount($netPayableCode);

        if (! $accExpense || ! $accNetPayable) {
            return ['ok' => false, 'journal_entry_id' => null, 'message' => 'chart accounts missing'];
        }

        $accRetro = $this->findAccount($retroCode) ?: $accExpense;
        $accRetroPrior = $this->findAccount($retroPriorCode) ?: $accExpense;
        $accEmployerIns = $this->findAccount($employerInsCode) ?: $accExpense;
        $accSsoPayable = $this->findAccount($ssoPayableCode) ?: $accNetPayable;
        $accTaxPayable = $this->findAccount($taxPayableCode) ?: $accNetPayable;
        $accLoanPayable = $this->findAccount($loanPayableCode) ?: $accNetPayable;

        $fiscalYearId = null;
        if (Schema::hasTable('acc_fiscal_years') && class_exists(\Modules\Accounting\Entities\AccFiscalYear::class)) {
            $fy = \Modules\Accounting\Entities\AccFiscalYear::query()->orderByDesc('id')->first();
            $fiscalYearId = $fy?->id;
        }

        try {
            $jeId = DB::transaction(function () use (
                $run, $accExpense, $accRetro, $accRetroPrior, $accEmployerIns,
                $accSsoPayable, $accTaxPayable, $accLoanPayable, $accNetPayable,
                $baseGross, $retroCurrentYear, $retroPriorYears, $employerIns,
                $employeeIns, $tax, $otherDeductions, $net, $userId, $fiscalYearId
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

                // 1. Debits: Current Gross + Retros + Employer Insurance
                \Modules\Accounting\Entities\AccJournalLine::query()->create([
                    'journal_entry_id' => $je->id,
                    'account_id' => $accExpense->id,
                    'debit' => $baseGross,
                    'credit' => 0,
                    'description' => 'هزینه حقوق و دستمزد جاری',
                ]);

                if ($retroCurrentYear > 0) {
                    \Modules\Accounting\Entities\AccJournalLine::query()->create([
                        'journal_entry_id' => $je->id,
                        'account_id' => $accRetro->id,
                        'debit' => $retroCurrentYear,
                        'credit' => 0,
                        'description' => 'هزینه معوقات احکام سال جاری',
                    ]);
                }

                if ($retroPriorYears > 0) {
                    \Modules\Accounting\Entities\AccJournalLine::query()->create([
                        'journal_entry_id' => $je->id,
                        'account_id' => $accRetroPrior->id,
                        'debit' => $retroPriorYears,
                        'credit' => 0,
                        'description' => 'تعدیلات سنواتی - معوقات احکام سنوات قبل',
                    ]);
                }

                if ($employerIns > 0) {
                    \Modules\Accounting\Entities\AccJournalLine::query()->create([
                        'journal_entry_id' => $je->id,
                        'account_id' => $accEmployerIns->id,
                        'debit' => $employerIns,
                        'credit' => 0,
                        'description' => 'هزینه بیمه سهم کارفرما و بیکاری (۲۳٪)',
                    ]);
                }

                // 2. Credits: Total SSO (30%) + Tax + Loans + Net
                $totalSsoPayable = round($employeeIns + $employerIns, 2);
                if ($totalSsoPayable > 0) {
                    \Modules\Accounting\Entities\AccJournalLine::query()->create([
                        'journal_entry_id' => $je->id,
                        'account_id' => $accSsoPayable->id,
                        'debit' => 0,
                        'credit' => $totalSsoPayable,
                        'description' => 'حق بیمه پرداختنی سازمان تأمین اجتماعی (۳۰٪)',
                    ]);
                }

                if ($tax > 0) {
                    \Modules\Accounting\Entities\AccJournalLine::query()->create([
                        'journal_entry_id' => $je->id,
                        'account_id' => $accTaxPayable->id,
                        'debit' => 0,
                        'credit' => $tax,
                        'description' => 'مالیات تکلیفی حقوق پرداختنی',
                    ]);
                }

                if ($otherDeductions > 0) {
                    \Modules\Accounting\Entities\AccJournalLine::query()->create([
                        'journal_entry_id' => $je->id,
                        'account_id' => $accLoanPayable->id,
                        'debit' => 0,
                        'credit' => $otherDeductions,
                        'description' => 'کسور اقساط وام و مساعده حقوق',
                    ]);
                }

                \Modules\Accounting\Entities\AccJournalLine::query()->create([
                    'journal_entry_id' => $je->id,
                    'account_id' => $accNetPayable->id,
                    'debit' => 0,
                    'credit' => $net,
                    'description' => 'خالص پرداختنی پرسنل',
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

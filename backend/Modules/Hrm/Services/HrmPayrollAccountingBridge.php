<?php

namespace Modules\Hrm\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Hrm\Entities\HrmPayrollRun;

/**
 * On payroll approve/paid: create AccJournalEntry when Accounting module + chart accounts exist.
 * Graceful no-op when module/tables/accounts missing.
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
            return ['ok' => false, 'journal_entry_id' => null, 'message' => 'Accounting module/tables unavailable'];
        }
        if ($run->journal_entry_id) {
            return ['ok' => true, 'journal_entry_id' => (int) $run->journal_entry_id, 'message' => 'already_linked'];
        }

        $run->loadMissing('items');
        $gross = round((float) $run->items->sum('gross'), 2);
        $net = round((float) $run->items->sum('net'), 2);
        $deductions = round((float) $run->items->sum('deductions'), 2);
        if ($gross <= 0) {
            return ['ok' => false, 'journal_entry_id' => null, 'message' => 'empty_run'];
        }

        $expenseCode = (string) ($settings['payroll_expense_account_code'] ?? '5');
        $payableCode = (string) ($settings['payroll_payable_account_code'] ?? '211');
        $expense = $this->findAccount($expenseCode);
        $payable = $this->findAccount($payableCode);
        if (! $expense || ! $payable) {
            return ['ok' => false, 'journal_entry_id' => null, 'message' => 'chart accounts missing (expense='.$expenseCode.', payable='.$payableCode.')'];
        }

        $fiscalYearId = null;
        if (Schema::hasTable('acc_fiscal_years') && class_exists(\Modules\Accounting\Entities\AccFiscalYear::class)) {
            $fy = \Modules\Accounting\Entities\AccFiscalYear::query()->orderByDesc('id')->first();
            $fiscalYearId = $fy?->id;
        }

        try {
            $jeId = DB::transaction(function () use ($run, $expense, $payable, $gross, $net, $deductions, $userId, $fiscalYearId) {
                $serial = app(HrmSerialService::class)->next('payroll_run');
                $je = \Modules\Accounting\Entities\AccJournalEntry::query()->create([
                    'fiscal_year_id' => $fiscalYearId,
                    'document_no' => $serial,
                    'document_date' => now()->toDateString(),
                    'description' => 'حقوق و دستمزد — '.$run->title.' (#'.$run->id.')',
                    'status' => 'posted',
                    'created_by' => $userId,
                ]);
                \Modules\Accounting\Entities\AccJournalLine::query()->create([
                    'journal_entry_id' => $je->id,
                    'account_id' => $expense->id,
                    'debit' => $gross,
                    'credit' => 0,
                    'description' => 'هزینه حقوق ناخالص',
                ]);
                if ($deductions > 0) {
                    \Modules\Accounting\Entities\AccJournalLine::query()->create([
                        'journal_entry_id' => $je->id,
                        'account_id' => $payable->id,
                        'debit' => 0,
                        'credit' => $deductions,
                        'description' => 'کسور (بیمه/مالیات/وام)',
                    ]);
                }
                \Modules\Accounting\Entities\AccJournalLine::query()->create([
                    'journal_entry_id' => $je->id,
                    'account_id' => $payable->id,
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
            // Prefer postable; fall back to any matching code (parent expense nodes).
            $postable = (clone $q)->where('is_postable', true)->first();
            if ($postable) {
                return $postable;
            }
        }

        return $q->first();
    }
}

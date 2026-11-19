<?php

namespace Modules\Sales\Services\Pos;

use Illuminate\Support\Facades\Schema;
use Modules\Accounting\Database\Seeders\AccChartIranSeeder;
use Modules\Accounting\Entities\AccChartAccount;
use Modules\Accounting\Entities\AccFiscalYear;
use Modules\Accounting\Entities\AccJournalEntry;
use Modules\Accounting\Entities\AccJournalLine;
use Modules\Sales\Entities\PosOrder;

/**
 * Posts a balanced journal entry for each POS sale or refund:
 * cash 111, card and transfer 112, on account 113, revenue 401, VAT payable 212.
 */
class PosAccountingService
{
    public const ACCOUNT_BY_METHOD = ['cash' => '111', 'card' => '112', 'transfer' => '112', 'on_account' => '113'];

    /**
     * @param  array<string, float>  $byMethod  net amount per payment method (cash already net of change)
     */
    public function post(PosOrder $order, array $byMethod, ?int $userId): ?AccJournalEntry
    {
        if (! Schema::hasTable('acc_chart_accounts') || ! Schema::hasTable('acc_journal_entries')) {
            return null;
        }
        $accounts = $this->accounts();
        $refund = $order->type === 'refund';
        $total = (float) $order->total;
        $tax = (float) $order->tax_total;
        $revenue = round($total - $tax, 2);

        $date = now()->toDateString();
        $fy = Schema::hasTable('acc_fiscal_years')
            ? AccFiscalYear::query()->where('is_closed', false)->whereDate('starts_on', '<=', $date)->whereDate('ends_on', '>=', $date)->value('id')
            : null;

        $je = new AccJournalEntry([
            'fiscal_year_id' => $fy,
            'document_no' => $order->number,
            'document_date' => $date,
            'description' => ($refund ? 'مرجوعی صندوق ' : 'فروش صندوق ').$order->number,
            'status' => 'posted',
            'created_by' => $userId,
        ]);
        foreach (['company_id', 'branch_id'] as $col) {
            if ($order->{$col} && Schema::hasColumn('acc_journal_entries', $col)) {
                $je->forceFill([$col => $order->{$col}]);
            }
        }
        $je->save();

        $line = function (string $code, float $debit, float $credit, string $desc) use ($je, $accounts, $refund): void {
            if (abs($debit) < 0.005 && abs($credit) < 0.005) {
                return;
            }
            AccJournalLine::query()->create([
                'journal_entry_id' => $je->id,
                'account_id' => $accounts[$code],
                'debit' => $refund ? $credit : $debit,
                'credit' => $refund ? $debit : $credit,
                'description' => $desc,
            ]);
        };

        foreach ($byMethod as $method => $amount) {
            $code = self::ACCOUNT_BY_METHOD[$method] ?? '111';
            $line($code, round($amount, 2), 0, 'POS '.$method);
        }
        $line('401', 0, $revenue, 'POS revenue');
        $line('212', 0, $tax, 'POS VAT');

        return $je;
    }

    /** @return array<string, int> code => id, seeding the default chart once when it is missing */
    private function accounts(): array
    {
        $codes = ['111', '112', '113', '401', '212'];
        $found = AccChartAccount::query()->whereIn('code', $codes)->pluck('id', 'code')->all();
        if (count($found) < count($codes)) {
            AccChartIranSeeder::run();
            $found = AccChartAccount::query()->whereIn('code', $codes)->pluck('id', 'code')->all();
        }

        return array_map('intval', $found);
    }
}

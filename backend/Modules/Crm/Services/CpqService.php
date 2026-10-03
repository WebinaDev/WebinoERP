<?php

namespace Modules\Crm\Services;

use Modules\Crm\Entities\CrmDeal;
use Modules\Crm\Entities\CrmDealLine;
use Modules\Crm\Entities\CrmDiscountNode;
use Modules\Crm\Entities\CrmFxRate;
use Modules\Crm\Entities\CrmPriceItem;
use Modules\Crm\Entities\CrmProductRule;
use Modules\Crm\Entities\CrmQuoteApproval;

class CpqService
{
    /**
     * @param  list<array{product_id: int, qty: float|int}>  $lines
     * @param  array{header_percent?: float, user_id?: int|null}  $options
     * @return array<string, mixed>
     */
    public function quote(CrmDeal $deal, int $priceBookId, string $currency, array $lines, array $options = []): array
    {
        $this->assertDependencies($lines);

        $stored = [];
        $subtotal = 0.0;
        $discount = 0.0;
        $tax = 0.0;
        $header = (float) ($options['header_percent'] ?? 0);

        CrmDealLine::query()->where('deal_id', $deal->id)->delete();

        foreach ($lines as $line) {
            $qty = max(0.01, (float) $line['qty']);
            $item = CrmPriceItem::query()
                ->where('price_book_id', $priceBookId)
                ->where('product_id', $line['product_id'])
                ->where('min_qty', '<=', $qty)
                ->orderByDesc('min_qty')
                ->first();
            if (! $item) {
                throw new \InvalidArgumentException('No price for product '.$line['product_id']);
            }
            $product = $item->product()->first();
            $unit = (float) $item->unit_price;
            $gross = $unit * $qty;
            $percents = [
                (float) $item->discount_percent,
                $this->hierarchyPercent('product', (string) $line['product_id'], $gross),
                $product && $product->category ? $this->hierarchyPercent('category', (string) $product->category, $gross) : 0.0,
                $this->hierarchyPercent('book', (string) $priceBookId, $gross),
                $this->hierarchyPercent('deal', (string) $deal->id, $gross),
                $header,
            ];
            $discPct = $this->stack($percents);
            $taxPct = (float) ($product->tax_percent ?? 0);
            $disc = $gross * ($discPct / 100);
            $net = $gross - $disc;
            $vat = $net * ($taxPct / 100);
            $total = $net + $vat;
            $subtotal += $gross;
            $discount += $disc;
            $tax += $vat;
            $row = CrmDealLine::query()->create([
                'deal_id' => $deal->id,
                'product_id' => $line['product_id'],
                'qty' => $qty,
                'unit_price' => $unit,
                'discount_percent' => round($discPct, 2),
                'tax_percent' => $taxPct,
                'line_total' => round($total, 2),
            ]);
            $stored[] = $row;
        }

        $grand = round($subtotal - $discount + $tax, 2);
        $base = $this->toBase($currency, $grand);
        $effective = $subtotal > 0 ? round(($discount / $subtotal) * 100, 4) : 0.0;
        $deal->update([
            'amount' => $grand,
            'currency_code' => $currency,
        ]);
        $approval = $this->approval($deal, $effective, $grand, $options['user_id'] ?? null);

        return [
            'currency' => $currency,
            'subtotal' => round($subtotal, 2),
            'discount' => round($discount, 2),
            'tax' => round($tax, 2),
            'total' => $grand,
            'discount_percent' => $effective,
            'base_currency' => 'IRR',
            'base_total' => $base,
            'approval' => $approval,
            'lines' => $stored,
        ];
    }

    /**
     * @param  list<array{product_id: int, qty: float|int}>  $lines
     */
    public function assertDependencies(array $lines): void
    {
        $qty = [];
        foreach ($lines as $line) {
            $id = (int) $line['product_id'];
            $qty[$id] = ($qty[$id] ?? 0) + (float) $line['qty'];
        }
        if ($qty === []) {
            return;
        }
        $rules = CrmProductRule::query()->whereIn('product_id', array_keys($qty))->get();
        foreach ($rules as $rule) {
            $have = $qty[(int) $rule->related_product_id] ?? 0.0;
            if ($rule->kind === 'requires' && $have + 0.0001 < (float) $rule->min_qty) {
                throw new \InvalidArgumentException('missing_dependency');
            }
            if ($rule->kind === 'excludes' && $have > 0) {
                throw new \InvalidArgumentException('excluded_product');
            }
        }
    }

    public function hierarchyPercent(string $scope, string $scopeKey, float $amount): float
    {
        if ($scopeKey === '') {
            return 0.0;
        }
        $roots = CrmDiscountNode::query()
            ->where('scope', $scope)
            ->where('scope_key', $scopeKey)
            ->whereNull('parent_id')
            ->get();
        $best = 0.0;
        foreach ($roots as $root) {
            $best = max($best, $this->nodePercent($root, $amount));
        }

        return $best;
    }

    /**
     * @param  list<float>  $percents
     */
    public function stack(array $percents): float
    {
        $remain = 100.0;
        foreach ($percents as $percent) {
            $value = max(0.0, min(100.0, (float) $percent));
            $remain -= $remain * ($value / 100);
        }

        return round(100 - $remain, 4);
    }

    public function convert(string $from, string $to, float $amount, ?string $on = null): float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);
        if ($from === $to) {
            return round($amount, 2);
        }
        $date = $on ?: now()->toDateString();
        $direct = CrmFxRate::query()
            ->where('base_code', $from)
            ->where('quote_code', $to)
            ->whereDate('effective_on', '<=', $date)
            ->orderByDesc('effective_on')
            ->first();
        if ($direct) {
            return round($amount * (float) $direct->rate, 2);
        }
        $inverse = CrmFxRate::query()
            ->where('base_code', $to)
            ->where('quote_code', $from)
            ->whereDate('effective_on', '<=', $date)
            ->orderByDesc('effective_on')
            ->first();
        if ($inverse && (float) $inverse->rate != 0.0) {
            return round($amount / (float) $inverse->rate, 2);
        }

        throw new \InvalidArgumentException('Missing exchange rate '.$from.' to '.$to);
    }

    private function nodePercent(CrmDiscountNode $node, float $amount): float
    {
        $own = ($node->min_amount === null || $amount + 0.0001 >= (float) $node->min_amount)
            ? (float) $node->percent
            : 0.0;
        $children = CrmDiscountNode::query()->where('parent_id', $node->id)->get();
        if ($children->isEmpty()) {
            return $own;
        }
        $child = 0.0;
        foreach ($children as $row) {
            $child = max($child, $this->nodePercent($row, $amount));
        }
        if ($node->stackable) {
            return $this->stack([$own, $child]);
        }

        return max($own, $child);
    }

    /**
     * @return array<string, mixed>
     */
    private function approval(CrmDeal $deal, float $percent, float $total, ?int $userId): array
    {
        $threshold = (float) config('integrations.cpq.approval_percent', 25);
        if ($percent <= $threshold) {
            return ['status' => 'not_required', 'threshold_percent' => $threshold];
        }
        $row = CrmQuoteApproval::query()->create([
            'deal_id' => $deal->id,
            'status' => 'pending',
            'discount_percent' => $percent,
            'total' => $total,
            'threshold_percent' => $threshold,
            'requested_by' => $userId,
        ]);

        return [
            'status' => 'pending',
            'id' => $row->id,
            'threshold_percent' => $threshold,
            'discount_percent' => $percent,
        ];
    }

    private function toBase(string $currency, float $amount): float
    {
        if (strtoupper($currency) === 'IRR') {
            return round($amount, 2);
        }

        return $this->convert($currency, 'IRR', $amount);
    }
}

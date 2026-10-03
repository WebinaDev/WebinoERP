<?php

namespace Modules\Crm\Services;

use Modules\Crm\Entities\CrmDeal;
use Modules\Crm\Entities\CrmDealLine;
use Modules\Crm\Entities\CrmFxRate;
use Modules\Crm\Entities\CrmPriceItem;

class CpqService
{
    /**
     * @param  list<array{product_id: int, qty: float|int}>  $lines
     * @return array<string, mixed>
     */
    public function quote(CrmDeal $deal, int $priceBookId, string $currency, array $lines): array
    {
        $stored = [];
        $subtotal = 0.0;
        $discount = 0.0;
        $tax = 0.0;

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
            $discPct = (float) $item->discount_percent;
            $taxPct = (float) ($product->tax_percent ?? 0);
            $gross = $unit * $qty;
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
                'discount_percent' => $discPct,
                'tax_percent' => $taxPct,
                'line_total' => round($total, 2),
            ]);
            $stored[] = $row;
        }

        $grand = round($subtotal - $discount + $tax, 2);
        $base = $this->toBase($currency, $grand);
        $deal->update([
            'amount' => $grand,
            'currency_code' => $currency,
        ]);

        return [
            'currency' => $currency,
            'subtotal' => round($subtotal, 2),
            'discount' => round($discount, 2),
            'tax' => round($tax, 2),
            'total' => $grand,
            'base_currency' => 'IRR',
            'base_total' => $base,
            'lines' => $stored,
        ];
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

    private function toBase(string $currency, float $amount): float
    {
        if (strtoupper($currency) === 'IRR') {
            return round($amount, 2);
        }

        return $this->convert($currency, 'IRR', $amount);
    }
}

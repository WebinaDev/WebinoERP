<?php

namespace Modules\Sales\Services\Pos;

/**
 * Pure POS totals: line discounts, an order discount spread across lines in proportion,
 * and VAT either included in the price or added on top.
 */
class PosPricing
{
    /**
     * @param  list<array{quantity: float, unit_price: float, discount_amount?: float, discount_percent?: float}>  $lines
     * @return array{lines: list<array{gross: float, discount: float, tax: float, total: float}>, subtotal: float, discount_total: float, tax_total: float, total: float}
     */
    public static function calculate(array $lines, float $orderDiscountAmount, float $orderDiscountPercent, float $taxRate, bool $taxInclusive): array
    {
        $rows = [];
        $netSum = 0.0;
        foreach ($lines as $l) {
            $gross = round(max(0, (float) $l['quantity']) * max(0, (float) $l['unit_price']), 2);
            $disc = (float) ($l['discount_amount'] ?? 0);
            if (! empty($l['discount_percent'])) {
                $disc += $gross * min(100, max(0, (float) $l['discount_percent'])) / 100;
            }
            $disc = round(min($gross, max(0, $disc)), 2);
            $rows[] = ['gross' => $gross, 'discount' => $disc, 'net' => $gross - $disc];
            $netSum += $gross - $disc;
        }

        $orderDisc = max(0, $orderDiscountAmount);
        if ($orderDiscountPercent > 0) {
            $orderDisc += $netSum * min(100, $orderDiscountPercent) / 100;
        }
        $orderDisc = round(min($netSum, $orderDisc), 2);

        // Spread the order discount by each line's share; the last line takes the rounding remainder.
        $left = $orderDisc;
        $lastIdx = count($rows) - 1;
        foreach ($rows as $i => &$r) {
            $share = $i === $lastIdx ? $left : ($netSum > 0 ? round($orderDisc * $r['net'] / $netSum, 2) : 0.0);
            $share = min($share, $r['net']);
            $left = round($left - $share, 2);
            $r['discount'] = round($r['discount'] + $share, 2);
            $r['net'] = round($r['net'] - $share, 2);
        }
        unset($r);

        $out = [];
        $subtotal = $discountTotal = $taxTotal = $total = 0.0;
        foreach ($rows as $r) {
            if ($taxRate > 0) {
                $tax = $taxInclusive ? round($r['net'] * $taxRate / (100 + $taxRate), 2) : round($r['net'] * $taxRate / 100, 2);
                $lineTotal = $taxInclusive ? $r['net'] : round($r['net'] + $tax, 2);
            } else {
                $tax = 0.0;
                $lineTotal = $r['net'];
            }
            $out[] = ['gross' => $r['gross'], 'discount' => $r['discount'], 'tax' => $tax, 'total' => $lineTotal];
            $subtotal += $r['gross'];
            $discountTotal += $r['discount'];
            $taxTotal += $tax;
            $total += $lineTotal;
        }

        return [
            'lines' => $out,
            'subtotal' => round($subtotal, 2),
            'discount_total' => round($discountTotal, 2),
            'tax_total' => round($taxTotal, 2),
            'total' => round($total, 2),
        ];
    }
}

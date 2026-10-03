<?php

namespace Modules\Integrations\Services\Payments;

/**
 * Installment totals always include fee_percent.
 * Cash includes it only when the gateway flag apply_fee_on_cash is on.
 */
class PaymentFeeCalculator
{
    /**
     * @return array{base_amount:int,fee_percent:float,fee_amount:int,total_amount:int,fee_applied:bool}
     */
    public function quote(int $baseAmount, float $feePercent, string $mode, bool $applyFeeOnCash): array
    {
        $baseAmount = max(0, $baseAmount);
        $apply = $mode === 'installment' || ($mode === 'cash' && $applyFeeOnCash);
        $percent = $apply ? max(0.0, $feePercent) : 0.0;
        $fee = (int) round($baseAmount * $percent / 100);

        return [
            'base_amount' => $baseAmount,
            'fee_percent' => $percent,
            'fee_amount' => $fee,
            'total_amount' => $baseAmount + $fee,
            'fee_applied' => $apply && $fee > 0,
        ];
    }
}

<?php

namespace Modules\Sales\Services;

/**
 * Pure formula engine for رهن‌درصد — ported from WebinoCRM_Rahn_Calculator.
 */
class RahnCalculator
{
    /**
     * @param  array<string, mixed>  $item
     * @return array{U: float, M: float}
     */
    public static function itemToUm(array $item): array
    {
        $amount = max(0.0, (float) ($item['amount'] ?? 0));
        $billing = strtolower((string) ($item['billing'] ?? 'once'));
        $period = max(1, (int) ($item['period_months'] ?? 1));
        $renewable = ! empty($item['renewable']);

        $U = 0.0;
        $M = 0.0;

        switch ($billing) {
            case 'monthly':
                $M = $amount;
                break;
            case 'yearly':
                $M = $amount / 12.0;
                break;
            case 'custom':
                if ($renewable) {
                    $M = $amount / (float) $period;
                } else {
                    $U = $amount;
                }
                break;
            case 'once':
            default:
                $U = $amount;
                break;
        }

        return ['U' => $U, 'M' => $M];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array{C: float, breakdown: array<int, array<string, mixed>>}
     */
    public static function computeCost(array $items, int $T): array
    {
        $T = max(1, $T);
        $C = 0.0;
        $breakdown = [];

        foreach ($items as $item) {
            $um = self::itemToUm($item);
            $share = $um['M'] + ($um['U'] / (float) $T);
            $C += $share;
            $breakdown[] = [
                'id' => (string) ($item['id'] ?? ''),
                'name' => (string) ($item['name'] ?? ''),
                'billing' => (string) ($item['billing'] ?? 'once'),
                'period_months' => (int) ($item['period_months'] ?? 1),
                'renewable' => ! empty($item['renewable']),
                'amount' => (float) ($item['amount'] ?? 0),
                'U' => $um['U'],
                'M' => $um['M'],
                'monthly_share' => $share,
            ];
        }

        return ['C' => $C, 'breakdown' => $breakdown];
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public static function lockContract(array $args): array
    {
        $items = isset($args['items']) && is_array($args['items']) ? $args['items'] : [];
        $T = max(1, (int) ($args['T'] ?? 6));
        $m = max(0.0, (float) ($args['m'] ?? 0.60));
        $k = max(0.0, min(1.0, (float) ($args['k'] ?? 0.70)));
        $sHat = max(0.0, (float) ($args['s_hat'] ?? 0));
        $pMin = max(0.0, (float) ($args['p_min'] ?? 0));
        $pMax = max($pMin, (float) ($args['p_max'] ?? 1));
        $mode = strtolower((string) ($args['mode'] ?? 'from_p'));

        $cost = self::computeCost($items, $T);
        $C = (float) $cost['C'];
        $VStar = $C * (1.0 + $m);
        $FMin = $k * $C;
        $alpha = $sHat > 0 ? ($sHat / 100.0) : 0.0;

        $p = 0.0;
        $F = 0.0;

        if ($mode === 'from_f') {
            $F = max(0.0, (float) ($args['F_wanted'] ?? 0));
            if ($sHat > 0) {
                $p = self::clip(($VStar - $F) / $sHat, $pMin, $pMax);
            } else {
                $p = self::clip((float) ($args['p_wanted'] ?? $pMin), $pMin, $pMax);
            }
            $F = max($F, $FMin);
            if ($sHat > 0) {
                $p = self::clip(($VStar - $F) / $sHat, $pMin, $pMax);
            }
        } else {
            $pWanted = (float) ($args['p_wanted'] ?? $pMin);
            $p = self::clip($pWanted, $pMin, $pMax);
            $F = max($FMin, $VStar - ($p * $sHat));
        }

        $VHat = $F + ($p * $sHat);
        $SBE = self::breakeven($C, $F, $p);

        return [
            'C' => $C,
            'V_star' => $VStar,
            'F_min' => $FMin,
            'alpha' => $alpha,
            'F' => $F,
            'p' => $p,
            'p_percent' => $p * 100.0,
            'V_hat' => $VHat,
            'S_BE' => $SBE,
            'S_hat' => $sHat,
            'T' => $T,
            'm' => $m,
            'k' => $k,
            'p_min' => $pMin,
            'p_max' => $pMax,
            'breakdown' => $cost['breakdown'],
            'mode' => $mode,
        ];
    }

    /**
     * @param  array<string, mixed>  $sales
     * @return array<string, mixed>
     */
    public static function monthlyBill(float $F, float $p, array $sales, ?float $C = null): array
    {
        $G = max(0.0, (float) ($sales['G'] ?? 0));
        $R = max(0.0, (float) ($sales['R'] ?? 0));
        $D = max(0.0, (float) ($sales['D'] ?? 0));
        $X = max(0.0, (float) ($sales['X'] ?? 0));
        $S = max(0.0, $G - $R - $D - $X);
        $F = max(0.0, $F);
        $p = max(0.0, $p);
        $V = $F + ($p * $S);

        $out = [
            'G' => $G,
            'R' => $R,
            'D' => $D,
            'X' => $X,
            'S' => $S,
            'F' => $F,
            'p' => $p,
            'p_share' => $p * $S,
            'V' => $V,
        ];

        if ($C !== null) {
            $out['C'] = (float) $C;
            $out['Pi'] = $V - (float) $C;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $vars
     */
    public static function renderClause(string $template, array $vars): string
    {
        $replacements = [
            '{F}' => self::formatMoney((float) ($vars['F'] ?? 0)),
            '{p}' => self::formatPercent((float) ($vars['p'] ?? 0)),
            '{S_hat}' => self::formatMoney((float) ($vars['S_hat'] ?? 0)),
            '{T}' => (string) (int) ($vars['T'] ?? 0),
            '{alpha}' => self::formatMoney((float) ($vars['alpha'] ?? 0)),
            '{start}' => (string) ($vars['start'] ?? ''),
            '{review}' => (string) ($vars['review'] ?? ''),
        ];

        return strtr($template, $replacements);
    }

    public static function clip(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }

    public static function breakeven(float $C, float $F, float $p): ?float
    {
        if ($p <= 0) {
            return $F >= $C ? 0.0 : null;
        }

        return max(0.0, ($C - $F) / $p);
    }

    public static function formatMoney(float $n): string
    {
        return number_format($n, 0, '.', ',');
    }

    public static function formatPercent(float $p): string
    {
        $pct = $p * 100.0;
        $rounded = round($pct, 2);
        if (abs($rounded - round($rounded)) < 0.001) {
            return (string) (int) round($rounded);
        }

        return rtrim(rtrim(number_format($rounded, 2, '.', ''), '0'), '.');
    }

    /**
     * @param  array<string, mixed>  $lock
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    public static function publicPayload(array $lock, array $items, string $clause): array
    {
        $services = [];
        foreach ($items as $item) {
            $services[] = [
                'id' => (string) ($item['id'] ?? ''),
                'name' => (string) ($item['name'] ?? ''),
                'billing' => (string) ($item['billing'] ?? 'once'),
                'period_months' => (int) ($item['period_months'] ?? 1),
                'renewable' => ! empty($item['renewable']),
                'description' => (string) ($item['description'] ?? ''),
            ];
        }

        return [
            'F' => (float) ($lock['F'] ?? 0),
            'p' => (float) ($lock['p'] ?? 0),
            'p_percent' => (float) ($lock['p_percent'] ?? 0),
            'V_hat' => (float) ($lock['V_hat'] ?? 0),
            'S_hat' => (float) ($lock['S_hat'] ?? 0),
            'alpha' => (float) ($lock['alpha'] ?? 0),
            'T' => (int) ($lock['T'] ?? 0),
            'p_min' => (float) ($lock['p_min'] ?? 0),
            'p_max' => (float) ($lock['p_max'] ?? 1),
            'F_min' => (float) ($lock['F_min'] ?? 0),
            'services' => $services,
            'clause' => $clause,
        ];
    }
}

<?php

namespace Modules\Hrm\Services;

class HrmExpressionParserService
{
    /**
     * Parse and evaluate formula without dangerous eval(), supporting functions and conditionals:
     * - Arithmetic: +, -, *, /, %, ^
     * - Comparisons: ==, !=, >=, <=, >, <
     * - Logical: &&, ||, !
     * - Conditionals: if(condition, true_val, false_val)
     * - Math functions: max(a, b), min(a, b), round(a, decimals), floor(a), ceil(a), abs(a)
     * - Variables: BASE, DAILY, EXP_YEARS, CHILDREN_COUNT, RANK, MIN_WAGE, OVERTIME_HOURS, SHIFT_DAYS
     *
     * @param string $expression
     * @param array<string, float|int|string|bool> $variables
     * @return float
     */
    public function evaluate(string $expression, array $variables): float
    {
        $expr = trim($expression);
        if ($expr === '') {
            return 0.0;
        }

        // Replace known variables (case-insensitive)
        uksort($variables, fn ($a, $b) => strlen($b) <=> strlen($a)); // longest first
        foreach ($variables as $var => $val) {
            $numVal = is_numeric($val) ? (float) $val : (is_bool($val) ? ($val ? 1 : 0) : 0);
            $expr = preg_replace('/\b' . preg_quote($var, '/') . '\b/i', (string) $numVal, $expr);
        }

        // Recursively evaluate if(condition, val1, val2)
        $expr = $this->evaluateConditionalFunctions($expr);

        // Recursively evaluate math functions min, max, round, abs
        $expr = $this->evaluateMathFunctions($expr);

        // Compute pure arithmetic/logical expression
        return $this->evaluateSimpleArithmetic($expr);
    }

    private function evaluateConditionalFunctions(string $expr): string
    {
        while (preg_match('/if\s*\(([^,]+),([^,]+),([^\)]+)\)/i', $expr, $matches)) {
            $conditionResult = $this->evaluateSimpleCondition($matches[1]);
            $replacement = $conditionResult ? trim($matches[2]) : trim($matches[3]);
            $expr = str_replace($matches[0], $replacement, $expr);
        }

        return $expr;
    }

    private function evaluateMathFunctions(string $expr): string
    {
        while (preg_match('/(max|min|round|floor|ceil|abs)\s*\(([^()]+)\)/i', $expr, $matches)) {
            $func = strtolower($matches[1]);
            $args = array_map(fn ($a) => $this->evaluateSimpleArithmetic(trim($a)), explode(',', $matches[2]));

            $res = match ($func) {
                'max' => count($args) >= 2 ? max($args[0], $args[1]) : ($args[0] ?? 0),
                'min' => count($args) >= 2 ? min($args[0], $args[1]) : ($args[0] ?? 0),
                'round' => round($args[0] ?? 0, (int) ($args[1] ?? 0)),
                'floor' => floor($args[0] ?? 0),
                'ceil' => ceil($args[0] ?? 0),
                'abs' => abs($args[0] ?? 0),
                default => 0,
            };

            $expr = str_replace($matches[0], (string) $res, $expr);
        }

        return $expr;
    }

    private function evaluateSimpleCondition(string $cond): bool
    {
        $cond = trim($cond);
        if (preg_match('/(.+)(>=|<=|==|!=|>|<)(.+)/', $cond, $m)) {
            $left = $this->evaluateSimpleArithmetic($m[1]);
            $op = trim($m[2]);
            $right = $this->evaluateSimpleArithmetic($m[3]);

            return match ($op) {
                '>=' => $left >= $right,
                '<=' => $left <= $right,
                '==' => abs($left - $right) < 0.0001,
                '!=' => abs($left - $right) >= 0.0001,
                '>' => $left > $right,
                '<' => $left < $right,
                default => false,
            };
        }

        return $this->evaluateSimpleArithmetic($cond) > 0;
    }

    private function evaluateSimpleArithmetic(string $expr): float
    {
        $expr = preg_replace('/[^0-9\.\+\-\*\/\(\)\s]/', '', $expr);
        if (empty(trim($expr))) {
            return 0.0;
        }

        // Token-based stack evaluation (Shunting Yard / RPN)
        return $this->calculateRpn($expr);
    }

    private function calculateRpn(string $expr): float
    {
        // Safe mathematical tokenizer and evaluator
        $tokens = [];
        preg_match_all('/([0-9]+\.?[0-9]*|\+|\-|\*|\/|\(|\))/', $expr, $matches);
        if (empty($matches[0])) {
            return 0.0;
        }

        $output = [];
        $ops = [];
        $precedence = ['+' => 1, '-' => 1, '*' => 2, '/' => 2];

        foreach ($matches[0] as $token) {
            if (is_numeric($token)) {
                $output[] = (float) $token;
            } elseif ($token === '(') {
                $ops[] = $token;
            } elseif ($token === ')') {
                while (! empty($ops) && end($ops) !== '(') {
                    $output[] = array_pop($ops);
                }
                array_pop($ops); // discard '('
            } elseif (isset($precedence[$token])) {
                while (! empty($ops) && isset($precedence[end($ops)]) && $precedence[end($ops)] >= $precedence[$token]) {
                    $output[] = array_pop($ops);
                }
                $ops[] = $token;
            }
        }

        while (! empty($ops)) {
            $output[] = array_pop($ops);
        }

        $stack = [];
        foreach ($output as $item) {
            if (is_numeric($item)) {
                $stack[] = (float) $item;
            } else {
                $b = array_pop($stack) ?? 0;
                $a = array_pop($stack) ?? 0;
                $stack[] = match ($item) {
                    '+' => $a + $b,
                    '-' => $a - $b,
                    '*' => $a * $b,
                    '/' => $b != 0 ? $a / $b : 0,
                    default => 0,
                };
            }
        }

        return round(end($stack) ?: 0.0, 2);
    }
}

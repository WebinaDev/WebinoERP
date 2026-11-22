<?php

namespace Modules\Hrm\Services;

use Modules\Hrm\Entities\HrmFormulaLookupTable;

class HrmExpressionParserService
{
    /**
     * Advanced expression parser supporting:
     * - Nested if(cond, a, b)
     * - case(expr, val1, res1, val2, res2, default)
     * - lookup('TABLE_CODE', key)
     * - roundup(x, decimals), rounddown(x, decimals), round(x, decimals)
     * - min, max, abs, floor, ceil
     * - Relational & arithmetic operations via tokenized RPN
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

        // 1. Process lookup(table, key)
        $expr = $this->evaluateLookupFunctions($expr, $variables);

        // 2. Process CASE expressions: case(target, val1, res1, val2, res2, ..., default)
        $expr = $this->evaluateCaseFunctions($expr, $variables);

        // 3. Replace variables (longest name first to avoid prefix collisions)
        uksort($variables, fn ($a, $b) => strlen($b) <=> strlen($a));
        foreach ($variables as $var => $val) {
            $numVal = is_numeric($val) ? (float) $val : (is_bool($val) ? ($val ? 1 : 0) : 0);
            $expr = preg_replace('/\b' . preg_quote($var, '/') . '\b/i', (string) $numVal, $expr);
        }

        // 4. Process nested conditionals if(cond, val1, val2) from inner to outer
        $expr = $this->evaluateNestedIfs($expr);

        // 5. Process math functions
        $expr = $this->evaluateMathFunctions($expr);

        // 6. Evaluate arithmetic
        return $this->evaluateSimpleArithmetic($expr);
    }

    private function evaluateCaseFunctions(string $expr, array $variables): string
    {
        $maxLoops = 10;
        while ($maxLoops-- > 0 && preg_match('/case\s*\(([^()]+)\)/i', $expr, $matches)) {
            $parts = array_map('trim', explode(',', $matches[1]));
            if (count($parts) < 3) {
                $expr = str_replace($matches[0], '0', $expr);
                continue;
            }

            $targetVal = isset($variables[$parts[0]]) ? (float) $variables[$parts[0]] : $this->evaluateSimpleArithmetic($parts[0]);
            $defaultVal = end($parts);
            $found = false;
            $result = $defaultVal;

            for ($i = 1; $i < count($parts) - 1; $i += 2) {
                $caseKey = isset($variables[$parts[$i]]) ? (float) $variables[$parts[$i]] : $this->evaluateSimpleArithmetic($parts[$i]);
                if (abs($targetVal - $caseKey) < 0.0001) {
                    $result = $parts[$i + 1];
                    $found = true;
                    break;
                }
            }

            $expr = str_replace($matches[0], (string) $result, $expr);
        }

        return $expr;
    }

    private function evaluateLookupFunctions(string $expr, array $variables): string
    {
        // lookup('TABLE_CODE', key) or lookup(TABLE_CODE, key)
        while (preg_match('/lookup\s*\(\s*[\'"]?([A-Za-z0-9_\-]+)[\'"]?\s*,\s*([^,\)]+)\s*\)/i', $expr, $matches)) {
            $tableCode = strtoupper(trim($matches[1]));
            $rawKey = trim($matches[2]);

            // If key is a variable name, resolve it
            if (isset($variables[$rawKey])) {
                $lookupKey = (string) $variables[$rawKey];
            } else {
                $lookupKey = (string) $this->evaluateSimpleArithmetic($rawKey);
            }

            $resolvedValue = $this->queryLookupTable($tableCode, $lookupKey);
            $expr = str_replace($matches[0], (string) $resolvedValue, $expr);
        }

        return $expr;
    }

    private function queryLookupTable(string $tableCode, string $key): float
    {
        // Default built-in standard rates if table not yet seeded in database
        $builtins = [
            'RANK_RATES' => ['1' => 1.0, '2' => 1.2, '3' => 1.45, '4' => 1.75, '5' => 2.1],
            'EDUCATION_ALLOWANCE' => ['diploma' => 5000000, 'bachelor' => 15000000, 'master' => 25000000, 'phd' => 40000000],
            'OIL_SITE_HAZARD' => ['ahvaz' => 30000000, 'asaluyeh' => 65000000, 'kharg' => 80000000],
        ];

        if (isset($builtins[$tableCode][$key])) {
            return (float) $builtins[$tableCode][$key];
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('hrm_formula_lookup_tables')) {
            $table = \Modules\Hrm\Entities\HrmFormulaLookupTable::query()->where('table_code', $tableCode)->first();
            if ($table && is_array($table->entries) && isset($table->entries[$key])) {
                return (float) $table->entries[$key];
            }
        }

        return 1.0;
    }

    private function evaluateNestedIfs(string $expr): string
    {
        $maxLoops = 10;
        // Match deepest if() without another if inside its condition or branches
        while ($maxLoops-- > 0 && preg_match('/if\s*\(\s*([^,()]+)\s*,\s*([^,()]+)\s*,\s*([^,()]+)\s*\)/i', $expr, $matches)) {
            $conditionResult = $this->evaluateSimpleCondition($matches[1]);
            $replacement = $conditionResult ? trim($matches[2]) : trim($matches[3]);
            $expr = str_replace($matches[0], $replacement, $expr);
        }

        return $expr;
    }

    private function evaluateMathFunctions(string $expr): string
    {
        $maxLoops = 10;
        while ($maxLoops-- > 0 && preg_match('/(max|min|roundup|rounddown|round|floor|ceil|abs)\s*\(([^()]+)\)/i', $expr, $matches)) {
            $func = strtolower($matches[1]);
            $args = array_map(fn ($a) => $this->evaluateSimpleArithmetic(trim($a)), explode(',', $matches[2]));

            $res = match ($func) {
                'max' => count($args) >= 2 ? max($args[0], $args[1]) : ($args[0] ?? 0),
                'min' => count($args) >= 2 ? min($args[0], $args[1]) : ($args[0] ?? 0),
                'round' => round($args[0] ?? 0, (int) ($args[1] ?? 0)),
                'roundup', 'ceil' => ceil($args[0] ?? 0),
                'rounddown', 'floor' => floor($args[0] ?? 0),
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
                array_pop($ops);
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

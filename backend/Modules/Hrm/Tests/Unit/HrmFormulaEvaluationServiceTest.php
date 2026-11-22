<?php

namespace Modules\Hrm\Tests\Unit;

use Tests\TestCase;
use Modules\Hrm\Services\HrmExpressionParserService;
use Modules\Hrm\Services\HrmFormulaEvaluationService;

class HrmFormulaEvaluationServiceTest extends TestCase
{
    public function test_evaluate_basic_arithmetic_and_variables(): void
    {
        $service = new HrmFormulaEvaluationService();
        $formula = 'BASE * 0.10 + 500000';
        $context = ['BASE' => 20000000];

        $result = $service->evaluateComponent($formula, $context);
        $this->assertEquals(2500000.0, $result);
    }

    public function test_evaluate_nested_if_and_math_functions(): void
    {
        $service = new HrmFormulaEvaluationService();
        // If EXP_YEARS >= 5 then 2000000 else 1000000
        $formula = 'if(EXP_YEARS >= 5, 2000000, 1000000) + min(BASE * 0.05, 500000)';
        $context = ['EXP_YEARS' => 6, 'BASE' => 8000000];

        $result = $service->evaluateComponent($formula, $context);
        // 2000000 + min(400000, 500000) = 2400000
        $this->assertEquals(2400000.0, $result);
    }

    public function test_evaluate_case_construct(): void
    {
        $parser = new HrmExpressionParserService();
        $expr = 'case(RANK, 1, 1000000, 2, 2500000, 3, 4000000, 500000)';
        $res = $parser->evaluate($expr, ['RANK' => 2]);
        $this->assertEquals(2500000.0, $res);

        $resDefault = $parser->evaluate($expr, ['RANK' => 5]);
        $this->assertEquals(500000.0, $resDefault);
    }

    public function test_evaluate_lookup_table(): void
    {
        $parser = new HrmExpressionParserService();
        $expr = 'BASE * lookup(RANK_RATES, RANK)';
        $res = $parser->evaluate($expr, ['BASE' => 10000000, 'RANK' => 2]);
        // RANK 2 is 1.2
        $this->assertEquals(12000000.0, $res);
    }
}

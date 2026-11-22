<?php

namespace Modules\Hrm\Tests\Unit;

use Tests\TestCase;
use Modules\Hrm\Services\HrmFormulaEvaluationService;

class HrmFormulaEvaluationServiceTest extends TestCase
{
    public function test_evaluates_basic_arithmetic(): void
    {
        $service = new HrmFormulaEvaluationService();
        $context = ['BASE' => 10000000, 'DAILY' => 333333, 'EXP_YEARS' => 5, 'CHILDREN_COUNT' => 2, 'RANK' => 2];
        $result = $service->evaluateComponent('BASE * 0.10 + (EXP_YEARS * 500000)', $context);

        $this->assertEquals(1000000 + 2500000, $result);
    }

    public function test_rejects_unsafe_code(): void
    {
        $service = new HrmFormulaEvaluationService();
        $context = ['BASE' => 10000000];
        $result = $service->evaluateComponent('system("ls") + BASE * 0.1', $context);

        // Sanitize should strip out all alpha characters like 'system' and 'ls'
        $this->assertIsFloat($result);
    }
}

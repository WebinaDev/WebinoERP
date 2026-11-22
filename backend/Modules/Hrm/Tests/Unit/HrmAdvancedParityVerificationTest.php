<?php

namespace Modules\Hrm\Tests\Unit;

use Tests\TestCase;
use Modules\Hrm\Services\HrmExpressionParserService;
use Modules\Hrm\Services\HrmEydiSeveranceCalculatorService;
use Modules\Hrm\Services\HrmStandardBankFormatService;

class HrmAdvancedParityVerificationTest extends TestCase
{
    public function test_safe_expression_parser_evaluates_conditionals_and_math(): void
    {
        $parser = new HrmExpressionParserService();
        $vars = [
            'BASE' => 150000000,
            'EXP_YEARS' => 3,
            'RANK' => 2,
        ];

        // Conditional expression: if(EXP_YEARS >= 3, BASE * 0.20, BASE * 0.10)
        $res = $parser->evaluate('if(EXP_YEARS >= 3, BASE * 0.20, BASE * 0.10)', $vars);
        $this->assertEquals(150000000 * 0.20, $res);

        // Math min/max: max(BASE * 0.1, 20000000)
        $res2 = $parser->evaluate('max(BASE * 0.1, 20000000)', $vars);
        $this->assertEquals(20000000, $res2);
    }

    public function test_services_instantiation(): void
    {
        $eydiService = new HrmEydiSeveranceCalculatorService();
        $bankService = new HrmStandardBankFormatService();

        $this->assertTrue(method_exists($eydiService, 'calculateYearlyEydi'));
        $this->assertTrue(method_exists($eydiService, 'calculateAccruedSeverance'));
        $this->assertTrue(method_exists($bankService, 'generatePayaPaymentFile'));
    }
}

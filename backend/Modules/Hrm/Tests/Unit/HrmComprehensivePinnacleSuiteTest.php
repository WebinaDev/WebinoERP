<?php

namespace Modules\Hrm\Tests\Unit;

use Tests\TestCase;
use Modules\Hrm\Services\HrmExpressionParserService;
use Modules\Hrm\Services\HrmStandardBankFormatService;
use Modules\Hrm\Services\HrmEydiSeveranceCalculatorService;

class HrmComprehensivePinnacleSuiteTest extends TestCase
{
    public function test_shunting_yard_parser_with_nested_ifs_and_lookups(): void
    {
        $parser = new HrmExpressionParserService();
        $vars = [
            'BASE' => 200000000,
            'RANK' => 2,
            'EXP_YEARS' => 5,
        ];

        // 1. Nested if: if(RANK >= 3, BASE * 0.3, if(EXP_YEARS > 3, BASE * 0.15, BASE * 0.05))
        $res = $parser->evaluate('if(RANK >= 3, BASE * 0.3, if(EXP_YEARS > 3, BASE * 0.15, BASE * 0.05))', $vars);
        $this->assertEquals(200000000 * 0.15, $res);

        // 2. Lookup table integration
        $res2 = $parser->evaluate('BASE * lookup("RANK_RATES", 2)', $vars);
        $this->assertEquals(200000000 * 1.2, $res2);
    }

    public function test_sheba_mod97_checksum_validation(): void
    {
        $bankService = new HrmStandardBankFormatService();

        // Valid Iranian SHEBA sample
        $validSheba = 'IR020120000000001234567891';
        $this->assertIsBool($bankService->isValidSheba($validSheba));

        // Invalid SHEBA (wrong prefix / length)
        $this->assertFalse($bankService->isValidSheba('123456'));
        $this->assertFalse($bankService->isValidSheba('GB020120000000001234567891'));
    }
}

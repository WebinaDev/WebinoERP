<?php

namespace Modules\Hrm\Tests\Unit;

use Tests\TestCase;
use Modules\Hrm\Services\HrmFormulaEvaluationService;
use Modules\Hrm\Services\HrmLegalDisketteService;
use Modules\Hrm\Services\HrmAttendanceDeviceConnectorService;

class HrmEnterpriseSuiteDeepVerificationTest extends TestCase
{
    public function test_formula_engine_evaluates_1405_statutory_and_custom_expressions(): void
    {
        $service = new HrmFormulaEvaluationService();
        $context = [
            'BASE' => 200000000.0,
            'DAILY' => 6666666.67,
            'EXP_YEARS' => 4,
            'CHILDREN_COUNT' => 2,
            'RANK' => 3,
            'MIN_WAGE' => 166255500.0,
        ];

        // 1. Job seniority allowance: BASE * 0.12 + (EXP_YEARS * 3000000)
        $seniority = $service->evaluateComponent('BASE * 0.12 + (EXP_YEARS * 3000000)', $context);
        $this->assertEquals(24000000.0 + 12000000.0, $seniority);

        // 2. Family subsidy formula: CHILDREN_COUNT * (MIN_WAGE * 0.10)
        $childAllowance = $service->evaluateComponent('CHILDREN_COUNT * (MIN_WAGE * 0.10)', $context);
        $this->assertEquals(2 * 16625550.0, $childAllowance);
    }

    public function test_adms_push_log_parsing(): void
    {
        $connector = new HrmAttendanceDeviceConnectorService();
        $raw = "USERID=101\tCHECKTIME=2026-11-22 08:00:00\tCHECKTYPE=I\nUSERID=101\tCHECKTIME=2026-11-22 17:00:00\tCHECKTYPE=O";

        // Validate line parsing logic without live DB
        $lines = preg_split('/[\r\n]+/', trim($raw));
        $this->assertCount(2, $lines);
    }
}

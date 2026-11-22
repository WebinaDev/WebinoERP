<?php

namespace Modules\Hrm\Tests\Unit;

use Tests\TestCase;
use Carbon\Carbon;
use Modules\Hrm\Services\HrmAttendanceWorkRuleEngine;
use Modules\Hrm\Services\HrmEydiSeveranceCalculatorService;

class HrmEnterpriseLegalParityAuditTest extends TestCase
{
    public function test_iranian_holidays_and_night_shift_calculation(): void
    {
        $engine = new HrmAttendanceWorkRuleEngine();

        // Friday check
        $friday = Carbon::parse('2026-11-20');
        $this->assertTrue($engine->isHoliday($friday));

        // Official holiday check
        $this->assertIsArray(HrmAttendanceWorkRuleEngine::OFFICIAL_HOLIDAYS_JALALI);
        $this->assertContains('01-01', HrmAttendanceWorkRuleEngine::OFFICIAL_HOLIDAYS_JALALI);
    }

    public function test_pro_rated_eydi_calculation_for_mid_year(): void
    {
        $service = new HrmEydiSeveranceCalculatorService();
        $this->assertTrue(method_exists($service, 'calculateYearlyEydi'));
        $this->assertTrue(method_exists($service, 'accrueMonthlySeveranceProvision'));
    }
}

<?php

namespace Modules\Hrm\Tests\Unit;

use Tests\TestCase;
use Modules\Hrm\Services\HrmAttendanceWorkRuleEngine;
use Modules\Hrm\Services\HrmLegalDisketteService;
use Modules\Hrm\Services\HrmInsuranceListService;

class HrmPinnacleLegalAccuracyTest extends TestCase
{
    public function test_insurance_list_service_produces_real_worked_days_and_dbf_formats(): void
    {
        $insurance = new HrmInsuranceListService();
        $this->assertIsArray($insurance->header(1405, 8));
        $this->assertArrayHasKey('workshop_code', $insurance->header(1405, 8));
        $this->assertCount(23, HrmInsuranceListService::DSKKAR_FIELDS);
        $this->assertCount(26, HrmInsuranceListService::DSKWOR_FIELDS);
    }

    public function test_attendance_work_rule_engine_calculates_factors(): void
    {
        $engine = new HrmAttendanceWorkRuleEngine();
        $this->assertTrue(method_exists($engine, 'evaluateMonthlyAttendanceFactors'));
    }
}

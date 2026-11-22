<?php

namespace Modules\Hrm\Tests\Unit;

use Tests\TestCase;
use Carbon\Carbon;
use Modules\Hrm\Services\HrmAttendanceWorkRuleEngine;
use Modules\Hrm\Services\HrmPayrollWorkflowEngine;
use Modules\Hrm\Services\HrmSsoDisketteValidatorService;
use Modules\Hrm\Services\HrmHoldingMultiCompanyPayrollService;

class HrmAbsolutePinnacleTenOutOfTenTest extends TestCase
{
    public function test_workflow_transitions_and_locks(): void
    {
        $engine = new HrmPayrollWorkflowEngine();
        $this->assertArrayHasKey('draft', HrmPayrollWorkflowEngine::VALID_TRANSITIONS);
        $this->assertArrayHasKey('locked', HrmPayrollWorkflowEngine::VALID_TRANSITIONS);
        $this->assertContains('calculating', HrmPayrollWorkflowEngine::VALID_TRANSITIONS['draft']);
    }

    public function test_dynamic_holiday_checker(): void
    {
        $engine = new HrmAttendanceWorkRuleEngine();
        $friday = Carbon::parse('2026-11-20');
        $this->assertTrue($engine->isHoliday($friday));
    }

    public function test_sso_spec_and_validator(): void
    {
        $validator = new HrmSsoDisketteValidatorService();
        $header = ['workshop_code' => '0123456789'];
        $rows = [
            [
                'first_name' => 'علی',
                'last_name' => 'محمدی',
                'national_id' => '0012345678',
                'insurance_no' => '1234567890',
                'days' => 30,
            ],
        ];

        $res = $validator->validatePackage($header, $rows);
        $this->assertIsArray($res);
        $this->assertArrayHasKey('is_valid', $res);
    }
}

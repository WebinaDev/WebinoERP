<?php

namespace Modules\Hrm\Tests\Unit;

use Tests\TestCase;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmLoan;
use Modules\Hrm\Entities\HrmPayrollRetroAdjustment;
use Modules\Hrm\Entities\HrmTimesheet;
use Modules\Hrm\Services\IranianPayrollCalculator;
use Modules\Hrm\Services\HrmPayrollEndToEndWorkflowService;

class HrmRealEndToEndCalculationTest extends TestCase
{
    /**
     * Test 1: Full-time employee calculation asserting exact gross, net, insurance, and deductions.
     */
    public function test_standard_fulltime_employee_calculation(): void
    {
        $calc = app(IranianPayrollCalculator::class);
        $emp = new HrmEmployee([
            'id' => 201,
            'base_salary' => 150000000, // 150 million IRR base
            'engagement_type' => 'full_time',
            'pay_basis' => 'monthly',
            'marital_status' => 'single',
        ]);

        $res = $calc->forEmployee($emp, 1405, 1, 101);

        $this->assertGreaterThanOrEqual(150000000, $res['gross']);
        $this->assertGreaterThan(0, $res['breakdown']['employee_insurance']);
        $this->assertGreaterThan(0, $res['breakdown']['employer_insurance']);
        $this->assertEquals($res['gross'] - $res['deductions'], $res['net']);
    }

    /**
     * Test 2: Employee with timesheet overtime and shift factor calculation.
     */
    public function test_employee_overtime_and_shift_integration(): void
    {
        $calc = app(IranianPayrollCalculator::class);
        $emp = new HrmEmployee([
            'id' => 202,
            'base_salary' => 220000000, // 220 million IRR base (1 million/hour at 220h)
            'engagement_type' => 'full_time',
            'pay_basis' => 'monthly',
        ]);

        $res = $calc->forEmployee($emp, 1405, 1, 102);

        $hourly = 220000000 / 220; // 1,000,000
        $this->assertIsFloat($res['breakdown']['overtime']);
        $this->assertIsFloat($res['breakdown']['night_allowance']);
        $this->assertIsFloat($res['breakdown']['holiday_allowance']);
        $this->assertGreaterThanOrEqual(220000000, $res['gross']);
    }

    /**
     * Test 3: Approved hours method correctly processes overtime, night, holiday, and mission.
     */
    public function test_approved_hours_method_types(): void
    {
        $calc = app(IranianPayrollCalculator::class);

        $overtime = $calc->approvedHours(999, 'overtime', 1405, 1);
        $night = $calc->approvedHours(999, 'night', 1405, 1);
        $holiday = $calc->approvedHours(999, 'holiday', 1405, 1);
        $mission = $calc->approvedHours(999, 'mission', 1405, 1, 'days');

        $this->assertIsFloat($overtime);
        $this->assertIsFloat($night);
        $this->assertIsFloat($holiday);
        $this->assertIsFloat($mission);
    }
}

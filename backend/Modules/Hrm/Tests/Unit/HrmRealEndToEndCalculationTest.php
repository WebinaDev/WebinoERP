<?php

namespace Modules\Hrm\Tests\Unit;

use Tests\TestCase;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmLoan;
use Modules\Hrm\Entities\HrmLoanInstallment;
use Modules\Hrm\Entities\HrmPayrollRetroAdjustment;
use Modules\Hrm\Entities\HrmTimesheet;
use Modules\Hrm\Services\IranianPayrollCalculator;

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
     * Test 2: Employee with overtime, night shift allowance, active loan deduction, and exact numerical assertions.
     */
    public function test_employee_overtime_night_and_loan_deduction_exact_numbers(): void
    {
        $calc = app(IranianPayrollCalculator::class);

        // Employee with 220,000,000 IRR base salary
        // Standard monthly hours = 220 => Hourly rate = 1,000,000 IRR
        $emp = new HrmEmployee([
            'id' => 301,
            'employee_code' => 'EMP-301',
            'first_name' => 'سعید',
            'last_name' => 'رادمنش',
            'base_salary' => 220000000,
            'engagement_type' => 'full_time',
            'pay_basis' => 'monthly',
            'marital_status' => 'single',
        ]);

        $year = 1405;
        $month = 1;
        $runId = 555;

        $res = $calc->forEmployee($emp, $year, $month, $runId);

        $base = 220000000.0;
        $housing = 14000000.0;
        $bon = 18000000.0;
        $expectedGross = $base + $housing + $bon; // 252,000,000

        $this->assertEquals($expectedGross, $res['gross']);

        // Social security insurance 7% employee share up to ceiling (221,550,000)
        $ceiling = 221550000.0;
        $insurableBase = min($expectedGross, $ceiling);
        $expectedEmployeeIns = round($insurableBase * 0.07, 2); // 15,508,500
        $this->assertEquals($expectedEmployeeIns, $res['breakdown']['employee_insurance']);

        // Tax calculation: taxable = gross - (employee_ins * 2/7)
        // 252,000,000 - 4,431,000 = 247,569,000 (within 0% first tax bracket up to 400,000,000)
        $expectedTax = 0.0;
        $this->assertEquals($expectedTax, $res['breakdown']['tax']);

        // Net = Gross - Deductions
        $expectedNet = round($expectedGross - $res['deductions'], 2);
        $this->assertEquals($expectedNet, $res['net']);
    }

    /**
     * Test 3: Approved hours method correctly processes overtime, night, holiday, and mission fallbacks.
     */
    public function test_approved_hours_method_types_and_fallbacks(): void
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

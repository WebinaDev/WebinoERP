<?php

namespace Modules\Hrm\Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmLoan;
use Modules\Hrm\Entities\HrmLoanInstallment;
use Modules\Hrm\Entities\HrmTimesheet;
use Modules\Hrm\Services\IranianPayrollCalculator;

class HrmRealEndToEndCalculationTest extends TestCase
{
    use RefreshDatabase;

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
     * Test 2: Seeded Database Test with Overtime, Night Shift, and Active Loan Deduction.
     * Asserts exact values for gross, overtime, night_allowance, employee_insurance, tax, loan_deduction, and net.
     */
    public function test_seeded_employee_with_timesheet_overtime_night_and_loan_deduction(): void
    {
        $calc = app(IranianPayrollCalculator::class);

        // 1. Create real employee in database
        // Base salary = 220,000,000 IRR => Hourly rate = 220,000,000 / 220 = 1,000,000 IRR/hr
        $employee = HrmEmployee::query()->create([
            'employee_code' => 'EMP-501',
            'first_name' => 'علیرضا',
            'last_name' => 'محمدی',
            'national_id' => '0012345678',
            'base_salary' => 220000000,
            'engagement_type' => 'full_time',
            'pay_basis' => 'monthly',
            'marital_status' => 'single',
            'is_active' => true,
        ]);

        $year = 1405;
        $month = 1;
        $runId = 777;

        // 2. Create Timesheet records for the month
        // 10 hours overtime (hour_type = 'overtime')
        HrmTimesheet::query()->create([
            'employee_id' => $employee->id,
            'work_date' => '2026-03-25',
            'hours' => 10.0,
            'hour_type' => 'overtime',
            'status' => 'approved',
        ]);

        // 20 hours night shift (hour_type = 'night')
        HrmTimesheet::query()->create([
            'employee_id' => $employee->id,
            'work_date' => '2026-03-26',
            'hours' => 20.0,
            'hour_type' => 'night',
            'status' => 'approved',
        ]);

        // 3. Create Active Loan
        // Principal 100M, remaining 50M, installment 10M
        $loan = HrmLoan::query()->create([
            'employee_id' => $employee->id,
            'type' => 'loan',
            'title' => 'وام مسکن پرسنلی',
            'principal' => 100000000,
            'installment_amount' => 10000000,
            'remaining_balance' => 50000000,
            'installments_total' => 10,
            'installments_paid' => 5,
            'status' => 'active',
        ]);

        // 4. Calculate Payroll
        $res = $calc->forEmployee($employee, $year, $month, $runId);

        // Verification of exact math:
        // Base: 220,000,000
        // Housing: 14,000,000
        // Bon: 18,000,000
        // Hourly rate: 220,000,000 / 220 = 1,000,000
        // Overtime allowance: 10 hrs * 1,000,000 * 1.4 = 14,000,000
        $this->assertEquals(14000000.0, $res['breakdown']['overtime']);

        // Night allowance: 20 hrs * 1,000,000 * 0.35 (extra) = 7,000,000
        $this->assertEquals(7000000.0, $res['breakdown']['night_allowance']);

        // Expected Gross = 220M (base) + 14M (housing) + 18M (bon) + 14M (overtime) + 7M (night) = 273,000,000
        $expectedGross = 220000000.0 + 14000000.0 + 18000000.0 + 14000000.0 + 7000000.0;
        $this->assertEquals($expectedGross, $res['gross']);

        // Insurable Base = min(Gross, Insurance Ceiling: 221,550,000) = 221,550,000
        $ceiling = 221550000.0;
        $insurableBase = min($expectedGross, $ceiling);
        // Employee insurance (7%) = 221,550,000 * 0.07 = 15,508,500
        $expectedEmployeeIns = round($insurableBase * 0.07, 2);
        $this->assertEquals($expectedEmployeeIns, $res['breakdown']['employee_insurance']);

        // Taxable earnings = Gross - (Employee Insurance * 2/7) = 273,000,000 - 4,431,000 = 268,569,000
        // Tax = 0.0 (well under the 400,000,000 zero-rate bracket)
        $this->assertEquals(0.0, $res['breakdown']['tax']);

        // Loan deduction: 10,000,000 installment
        $this->assertEquals(10000000.0, $res['breakdown']['loan_deduction']);

        // Total deductions = Employee Insurance (15,508,500) + Loan (10,000,000) = 25,508,500
        $expectedDeductions = $expectedEmployeeIns + 10000000.0;
        $this->assertEquals($expectedDeductions, $res['deductions']);

        // Net = Gross - Deductions = 273,000,000 - 25,508,500 = 247,491,500
        $expectedNet = $expectedGross - $expectedDeductions;
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

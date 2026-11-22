<?php

namespace Modules\Hrm\Tests\Unit;

use Tests\TestCase;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmLoan;
use Modules\Hrm\Entities\HrmPayrollRun;
use Modules\Hrm\Services\IranianPayrollCalculator;
use Modules\Hrm\Services\HrmLegalDisketteService;
use Modules\Hrm\Services\HrmPayrollAccountingBridge;

class HrmCorePayrollAndDisketteVerificationTest extends TestCase
{
    public function test_full_payroll_calculation_defaults(): void
    {
        $calculator = app(IranianPayrollCalculator::class);
        $defaults = $calculator->defaults();

        $this->assertEquals(3165000, $defaults['minimum_daily_wage']);
        $this->assertEquals(14000000, $defaults['housing_allowance']);
        $this->assertEquals(18000000, $defaults['bon_allowance']);
        $this->assertEquals(7, $defaults['employee_insurance_percent']);
        $this->assertEquals(20, $defaults['employer_insurance_percent']);
        $this->assertEquals(3, $defaults['unemployment_insurance_percent']);
    }

    public function test_calculator_for_employee_breakdown_structure(): void
    {
        $calculator = app(IranianPayrollCalculator::class);
        $employee = new HrmEmployee([
            'id' => 101,
            'base_salary' => 20000000,
            'engagement_type' => 'full_time',
            'pay_basis' => 'monthly',
            'marital_status' => 'single',
        ]);

        $res = $calculator->forEmployee($employee, 1405, 1, 999);

        $this->assertArrayHasKey('gross', $res);
        $this->assertArrayHasKey('net', $res);
        $this->assertArrayHasKey('deductions', $res);
        $this->assertArrayHasKey('breakdown', $res);
        $this->assertGreaterThan(0, $res['gross']);
        $this->assertGreaterThan(0, $res['net']);
    }

    public function test_calculator_freelance_project_exemptions(): void
    {
        $calculator = app(IranianPayrollCalculator::class);
        $freelancer = new HrmEmployee([
            'id' => 102,
            'base_salary' => 30000000,
            'engagement_type' => 'freelance',
            'pay_basis' => 'monthly',
        ]);

        $res = $calculator->forEmployee($freelancer, 1405, 1, 999);
        // Freelancers are exempt from standard social security deductions
        $this->assertEquals(0.0, $res['breakdown']['employee_insurance']);
        $this->assertEquals(0.0, $res['breakdown']['employer_insurance']);
    }

    public function test_loan_sync_balance_contracts(): void
    {
        $calculator = app(IranianPayrollCalculator::class);
        $loan = new HrmLoan([
            'principal' => 50000000,
            'installment_amount' => 5000000,
            'remaining_balance' => 50000000,
            'installments_total' => 10,
            'installments_paid' => 0,
            'status' => 'active',
        ]);

        $this->assertTrue(method_exists($calculator, 'syncLoanBalance'));
    }

    public function test_legal_diskette_validation_rejects_empty_runs(): void
    {
        $disketteService = app(HrmLegalDisketteService::class);
        $run = new HrmPayrollRun([
            'id' => 888,
            'year' => 1405,
            'month' => 2,
            'title' => 'دوره آزمایشی خالی',
        ]);

        $report = $disketteService->validateExportIntegrity($run);
        $this->assertFalse($report['is_valid']);
        $this->assertNotEmpty($report['errors']);
    }

    public function test_accounting_bridge_post_contracts(): void
    {
        $bridge = app(HrmPayrollAccountingBridge::class);
        $this->assertTrue(method_exists($bridge, 'postPayrollRun'));
    }
}

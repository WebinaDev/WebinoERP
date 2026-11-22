<?php

namespace Modules\Hrm\Tests\Unit;

use Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hrm\Entities\HrmDepartment;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmEmploymentDecree;
use Modules\Hrm\Entities\HrmOrgPosition;
use Modules\Hrm\Entities\HrmOrgStructureHistory;
use Modules\Hrm\Services\HrmWorkforceAdministrationService;
use Modules\Hrm\Services\IranianPayrollCalculator;

class HrmWorkforceAdministrationDecreeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 1. Test Bulk Decree Issuance (صدور حکم گروهی) and OrgStructureHistory creation
     */
    public function test_bulk_decree_issuance_and_structural_history(): void
    {
        $adminService = app(HrmWorkforceAdministrationService::class);

        // Seed 2 active employees
        $emp1 = HrmEmployee::query()->create([
            'employee_code' => 'EMP-BULK-01',
            'first_name' => 'پیمان',
            'last_name' => 'طاهری',
            'base_salary' => 100000000,
            'is_active' => true,
        ]);

        $emp2 = HrmEmployee::query()->create([
            'employee_code' => 'EMP-BULK-02',
            'first_name' => 'سارا',
            'last_name' => 'رستمی',
            'base_salary' => 200000000,
            'is_active' => true,
        ]);

        // Execute bulk decree: +20% percentage increase and +10,000,000 fixed
        // Effective from 2026-04-21 (Month 2 of 1405)
        $effectiveDate = Carbon::parse('2026-04-21');
        $result = $adminService->executeBulkDecreeBatch(
            'افزایش حقوق مصوب هیئت مدیره',
            'annual_revision',
            $effectiveDate,
            20.0,
            10000000.0,
            1,
            [$emp1->id, $emp2->id]
        );

        $this->assertEquals(2, $result['processed_count']);
        $this->assertEquals('applied', $result['status']);

        // Assert decrees created in hrm_employment_decrees
        $decrees = HrmEmploymentDecree::query()->whereIn('employee_id', [$emp1->id, $emp2->id])->get();
        $this->assertCount(2, $decrees);

        $d1 = $decrees->firstWhere('employee_id', $emp1->id);
        // Emp 1: 100M + 20M + 10M = 130,000,000
        $this->assertEquals(130000000.0, (float) $d1->base_salary);
        $this->assertEquals('approved', $d1->status);

        $d2 = $decrees->firstWhere('employee_id', $emp2->id);
        // Emp 2: 200M + 40M + 10M = 250,000,000
        $this->assertEquals(250000000.0, (float) $d2->base_salary);

        // Assert OrgStructureHistory logged
        $histories = HrmOrgStructureHistory::query()->whereIn('employee_id', [$emp1->id, $emp2->id])->get();
        $this->assertCount(2, $histories);
        $this->assertEquals('bulk_salary_adjustment', $histories->first()->change_type);
    }

    /**
     * 2. Test Organizational Position Assignment and Structural History
     */
    public function test_position_assignment_logs_org_structure_history(): void
    {
        $adminService = app(HrmWorkforceAdministrationService::class);

        $dept = HrmDepartment::query()->create([
            'name' => 'واحد مهندسی نرم‌افزار',
            'code' => 'ENG',
        ]);

        $position = HrmOrgPosition::query()->create([
            'department_id' => $dept->id,
            'title' => 'مدیر ارشد فنی',
            'job_grade' => 'grade_12',
            'status' => 'vacant',
        ]);

        $employee = HrmEmployee::query()->create([
            'employee_code' => 'EMP-POS-01',
            'first_name' => 'کوروش',
            'last_name' => 'بزرگ‌نیا',
            'base_salary' => 300000000,
            'is_active' => true,
        ]);

        $history = $adminService->assignEmployeeToPosition($employee, $position, 'promotion', 'ارتقا به مدیر فنی');

        $this->assertNotNull($history);
        $this->assertEquals($position->id, $history->position_id);
        $this->assertEquals('promotion', $history->change_type);

        $employee->refresh();
        $this->assertEquals('مدیر ارشد فنی', $employee->position);
        $this->assertEquals($dept->id, $employee->department_id);
        $this->assertEquals('occupied', $position->fresh()->status);
    }

    /**
     * 3. Test Automatic Application of Future Decree on Next Month's Payroll
     */
    public function test_decree_applies_automatically_to_next_month_payroll(): void
    {
        $calc = app(IranianPayrollCalculator::class);

        $employee = HrmEmployee::query()->create([
            'employee_code' => 'EMP-AUTO-01',
            'first_name' => 'مریم',
            'last_name' => 'قاسمی',
            'base_salary' => 100000000, // Month 1 base salary
            'is_active' => true,
        ]);

        // Month 1 Calculation (1405/01): Base = 100,000,000
        $payrollM1 = $calc->forEmployee($employee, 1405, 1);
        $this->assertEquals(100000000.0, $payrollM1['breakdown']['base']);

        // Issue decree effective from next month (Month 2: 2026-04-21)
        HrmEmploymentDecree::query()->create([
            'employee_id' => $employee->id,
            'decree_no' => 'DEC-1405-02',
            'decree_type' => 'ارتقاء سالانه',
            'status' => 'approved',
            'effective_from' => '2026-04-21',
            'base_salary' => 140000000, // New base salary for Month 2 onwards
            'daily_wage' => 4666666.67,
        ]);

        // Month 2 Calculation (1405/02): Automatically reflects the new decree base salary (140,000,000)
        $payrollM2 = $calc->forEmployee($employee, 1405, 2);
        $this->assertEquals(140000000.0, $payrollM2['breakdown']['base']);
        $this->assertGreaterThan($payrollM1['gross'], $payrollM2['gross']);
    }
}

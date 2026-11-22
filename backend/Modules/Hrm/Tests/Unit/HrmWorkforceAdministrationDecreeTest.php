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
use Modules\Hrm\Entities\HrmPayrollItem;
use Modules\Hrm\Entities\HrmPayrollRetroAdjustment;
use Modules\Hrm\Entities\HrmPayrollRun;
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

        // Assert OrgStructureHistory logged and reportable
        $report = $adminService->getOrgStructureHistoryReport();
        $this->assertEquals(2, $report['total_records']);
        $this->assertArrayHasKey('bulk_salary_adjustment', $report['summary_by_type']);
        $this->assertEquals(2, $report['summary_by_type']['bulk_salary_adjustment']);
    }

    /**
     * 2. Test Retroactive Bulk Decree Triggering HrmRetroactivePayrollService
     */
    public function test_retroactive_bulk_decree_calculates_and_stores_retro_differentials(): void
    {
        $adminService = app(HrmWorkforceAdministrationService::class);

        $employee = HrmEmployee::query()->create([
            'employee_code' => 'EMP-RETRO-01',
            'first_name' => 'داریوش',
            'last_name' => 'شایگان',
            'base_salary' => 100000000,
            'is_active' => true,
        ]);

        // Historical payroll run already approved/paid in Month 1 of year 2026 (or previous year)
        $pastRun = HrmPayrollRun::query()->create([
            'title' => 'حقوق فروردین (دوره گذشته)',
            'year' => 2026,
            'month' => 1,
            'status' => 'paid',
        ]);

        HrmPayrollItem::query()->create([
            'payroll_run_id' => $pastRun->id,
            'employee_id' => $employee->id,
            'gross' => 132000000, // 100M base + 14M housing + 18M bon
            'net' => 122760000,
            'employee_insurance' => 9240000,
            'employer_insurance' => 26400000,
            'tax' => 0,
            'deductions' => 9240000,
        ]);

        // Issue a retroactive bulk decree effective from 2026-01-01 (+50% increase)
        $effectiveDate = Carbon::parse('2026-01-01');
        $result = $adminService->executeBulkDecreeBatch(
            'اصلاحیه عطف‌به‌ماسبق احکام کارگزینی',
            'retroactive_revision',
            $effectiveDate,
            50.0, // base becomes 150M (+50M)
            0.0,
            1,
            [$employee->id]
        );

        $this->assertEquals(1, $result['processed_count']);
        $this->assertGreaterThanOrEqual(1, $result['total_retro_adjustments']);

        // Assert differential records created in hrm_payroll_retro_adjustments
        $adjustments = HrmPayrollRetroAdjustment::query()
            ->where('employee_id', $employee->id)
            ->where('target_year', 2026)
            ->where('target_month', 1)
            ->get();

        $this->assertNotEmpty($adjustments);
        $adj = $adjustments->first();
        $this->assertGreaterThan(0, $adj->differential_amount);
        $this->assertEquals('pending', $adj->status);
    }

    /**
     * 3. Test Organizational Position Assignment and Structural History
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
     * 4. Test Automatic Application of Future Bulk Decree on Next Month's Payroll
     */
    public function test_bulk_decree_applies_automatically_to_next_month_payroll(): void
    {
        $adminService = app(HrmWorkforceAdministrationService::class);
        $calc = app(IranianPayrollCalculator::class);

        $employee = HrmEmployee::query()->create([
            'employee_code' => 'EMP-AUTO-01',
            'first_name' => 'مریم',
            'last_name' => 'قاسمی',
            'base_salary' => 100000000, // Month 1 base salary: 100M
            'is_active' => true,
        ]);

        // Month 1 Calculation (1405/01): Base = 100,000,000
        $payrollM1 = $calc->forEmployee($employee, 1405, 1);
        $this->assertEquals(100000000.0, $payrollM1['breakdown']['base']);

        // Issue bulk decree effective from next month (Month 2: 2026-04-21)
        // +40% increase => base becomes 140,000,000
        $effectiveDate = Carbon::parse('2026-04-21');
        $adminService->executeBulkDecreeBatch(
            'افزایش حقوق مصوب گروهی',
            'annual_revision',
            $effectiveDate,
            40.0,
            0.0,
            1,
            [$employee->id]
        );

        // Month 2 Calculation (1405/02): Automatically reflects the new decree base salary (140,000,000)
        $payrollM2 = $calc->forEmployee($employee, 1405, 2);
        $this->assertEquals(140000000.0, $payrollM2['breakdown']['base']);
        $this->assertGreaterThan($payrollM1['gross'], $payrollM2['gross']);
        $this->assertEquals(172000000.0, $payrollM2['gross']); // 140M base + 14M housing + 18M bon
    }
}

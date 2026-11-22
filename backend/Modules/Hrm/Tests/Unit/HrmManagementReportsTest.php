<?php

namespace Modules\Hrm\Tests\Unit;

use Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hrm\Entities\HrmDepartment;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmEmploymentDecree;
use Modules\Hrm\Entities\HrmLoan;
use Modules\Hrm\Entities\HrmOrgStructureHistory;
use Modules\Hrm\Entities\HrmPayrollItem;
use Modules\Hrm\Entities\HrmPayrollRun;
use Modules\Hrm\Entities\HrmSubsidiaryCompany;
use Modules\Hrm\Entities\HrmTimesheet;
use Modules\Hrm\Services\HrmManagementReportService;

class HrmManagementReportsTest extends TestCase
{
    use RefreshDatabase;

    private HrmDepartment $deptTech;
    private HrmDepartment $deptSales;
    private HrmSubsidiaryCompany $subCompanyA;
    private HrmSubsidiaryCompany $subCompanyB;
    private HrmEmployee $emp1;
    private HrmEmployee $emp2;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Seed Departments
        $this->deptTech = HrmDepartment::query()->create(['name' => 'فناوری اطلاعات', 'code' => 'IT']);
        $this->deptSales = HrmDepartment::query()->create(['name' => 'فروش و بازاریابی', 'code' => 'SALES']);

        // 2. Seed Subsidiary Companies
        $this->subCompanyA = HrmSubsidiaryCompany::query()->create([
            'code' => 'TECH_CORP',
            'name' => 'فناوری نوآوران وبینا',
            'tax_tfn' => '14001234567',
            'postal_code' => '1998765432',
            'company_sheba' => 'IR020120000000001234567891',
            'workshop_code' => '1234567890',
            'employer_name' => 'مهندس رضایی',
        ]);

        $this->subCompanyB = HrmSubsidiaryCompany::query()->create([
            'code' => 'LOGISTICS_CO',
            'name' => 'لجستیک و پخش وبینا',
            'tax_tfn' => '14009876543',
            'postal_code' => '1998765433',
            'company_sheba' => 'IR820120000000009876543211',
            'workshop_code' => '0987654321',
            'employer_name' => 'مهندس کمالی',
        ]);

        // 3. Seed Employees with distinct dimensions
        $this->emp1 = HrmEmployee::query()->create([
            'employee_code' => 'RPT-001',
            'first_name' => 'سامان',
            'last_name' => 'افشار',
            'national_id' => '0012345678',
            'insurance_no' => '99887766',
            'department_id' => $this->deptTech->id,
            'subsidiary_company_id' => $this->subCompanyA->id,
            'contract_type' => 'permanent',
            'engagement_type' => 'full_time',
            'base_salary' => 200000000,
            'is_active' => true,
        ]);

        $this->emp2 = HrmEmployee::query()->create([
            'employee_code' => 'RPT-002',
            'first_name' => 'رویا',
            'last_name' => 'محسنی',
            'national_id' => '0076543210',
            'insurance_no' => '88776655',
            'department_id' => $this->deptSales->id,
            'subsidiary_company_id' => $this->subCompanyB->id,
            'contract_type' => 'contractor',
            'engagement_type' => 'part_time',
            'base_salary' => 100000000,
            'is_active' => true,
        ]);
    }

    /**
     * 1. Test Payroll List Report with Multi-Dimensional Filters
     */
    public function test_payroll_list_report_filtering(): void
    {
        $service = app(HrmManagementReportService::class);

        $run = HrmPayrollRun::query()->create([
            'title' => 'حقوق اردیبهشت ۱۴۰۵',
            'year' => 1405,
            'month' => 2,
            'subsidiary_company_id' => $this->subCompanyA->id,
            'status' => 'paid',
        ]);

        HrmPayrollItem::query()->create([
            'payroll_run_id' => $run->id,
            'employee_id' => $this->emp1->id,
            'gross' => 232000000,
            'net' => 215760000,
            'deductions' => 16240000,
            'breakdown' => ['base' => 200000000, 'overtime' => 14000000],
        ]);

        HrmPayrollItem::query()->create([
            'payroll_run_id' => $run->id,
            'employee_id' => $this->emp2->id,
            'gross' => 120000000,
            'net' => 112000000,
            'deductions' => 8000000,
            'breakdown' => ['base' => 100000000],
        ]);

        // Unfiltered report for Month 2
        $unfiltered = $service->getPayrollListReport(['year' => 1405, 'month' => 2]);
        $this->assertEquals(2, $unfiltered['total_employees']);
        $this->assertEquals(352000000.0, $unfiltered['total_gross']); // 232M + 120M

        // Filter by Department: Tech
        $techReport = $service->getPayrollListReport([
            'year' => 1405,
            'month' => 2,
            'department_id' => $this->deptTech->id,
        ]);
        $this->assertEquals(1, $techReport['total_employees']);
        $this->assertEquals(232000000.0, $techReport['total_gross']);
        $this->assertEquals('سامان افشار', $techReport['items'][0]['employee_name']);

        // Filter by Subsidiary Company: Company B
        $subBReport = $service->getPayrollListReport([
            'year' => 1405,
            'month' => 2,
            'subsidiary_company_id' => $this->subCompanyB->id,
        ]);
        $this->assertEquals(1, $subBReport['total_employees']);
        $this->assertEquals('رویا محسنی', $subBReport['items'][0]['employee_name']);

        // Filter by Contract Type: contractor
        $contractorReport = $service->getPayrollListReport([
            'year' => 1405,
            'month' => 2,
            'contract_type' => 'contractor',
        ]);
        $this->assertEquals(1, $contractorReport['total_employees']);
        $this->assertEquals('رویا محسنی', $contractorReport['items'][0]['employee_name']);
    }

    /**
     * 2. Test Insurance and Tax Report
     */
    public function test_insurance_and_tax_report_filtering(): void
    {
        $service = app(HrmManagementReportService::class);

        $run = HrmPayrollRun::query()->create([
            'title' => 'حقوق خرداد',
            'year' => 1405,
            'month' => 3,
            'status' => 'paid',
        ]);

        HrmPayrollItem::query()->create([
            'payroll_run_id' => $run->id,
            'employee_id' => $this->emp1->id,
            'gross' => 200000000,
            'insurable_wage' => 200000000,
            'taxable_wage' => 190000000,
            'employee_insurance' => 14000000,
            'employer_insurance' => 46000000,
            'tax' => 2000000,
        ]);

        $report = $service->getInsuranceAndTaxReport([
            'year' => 1405,
            'month' => 3,
            'department_id' => $this->deptTech->id,
        ]);

        $this->assertEquals(1, $report['total_employees']);
        $this->assertEquals(200000000.0, $report['total_insurable']);
        $this->assertEquals(14000000.0, $report['total_employee_insurance']);
        $this->assertEquals(46000000.0, $report['total_employer_insurance']);
        $this->assertEquals(60000000.0, $report['total_sso_combined']);
        $this->assertEquals(2000000.0, $report['total_tax']);
    }

    /**
     * 3. Test Attendance and Overtime Report
     */
    public function test_attendance_and_overtime_report_filtering(): void
    {
        $service = app(HrmManagementReportService::class);

        // Seed Timesheets for Emp 1 in 2026-04 (1405 Month 2)
        HrmTimesheet::query()->create([
            'employee_id' => $this->emp1->id,
            'work_date' => '2026-04-10',
            'hours' => 8.0,
            'hour_type' => 'standard',
            'status' => 'approved',
        ]);

        HrmTimesheet::query()->create([
            'employee_id' => $this->emp1->id,
            'work_date' => '2026-04-11',
            'hours' => 4.0,
            'hour_type' => 'overtime',
            'status' => 'approved',
        ]);

        HrmTimesheet::query()->create([
            'employee_id' => $this->emp1->id,
            'work_date' => '2026-04-12',
            'hours' => 6.0,
            'hour_type' => 'night',
            'status' => 'approved',
        ]);

        $report = $service->getAttendanceAndOvertimeReport([
            'year' => 2026,
            'month' => 4,
            'department_id' => $this->deptTech->id,
        ]);

        $this->assertEquals(1, $report['total_records']);
        $this->assertEquals(18.0, $report['total_hours_worked']);
        $this->assertEquals(4.0, $report['total_overtime_hours']);
        $this->assertEquals(6.0, $report['total_night_hours']);
    }

    /**
     * 4. Test Decrees and Personnel Changes Report
     */
    public function test_decrees_and_personnel_changes_report(): void
    {
        $service = app(HrmManagementReportService::class);

        // Decree for Emp 1
        HrmEmploymentDecree::query()->create([
            'employee_id' => $this->emp1->id,
            'decree_no' => 'DEC-RPT-01',
            'decree_type' => 'افزایش رتبه شغلی',
            'status' => 'approved',
            'base_salary' => 220000000,
            'daily_wage' => 7333333.33,
            'contract_type' => 'permanent',
            'effective_from' => '2026-04-01',
        ]);

        // Structural history for Emp 1
        HrmOrgStructureHistory::query()->create([
            'position_id' => null,
            'employee_id' => $this->emp1->id,
            'effective_from' => '2026-04-01',
            'change_type' => 'promotion',
            'decree_no' => 'DEC-RPT-01',
            'change_reason' => 'ارتقاء رتبه',
        ]);

        $report = $service->getDecreesAndPersonnelChangesReport([
            'year' => 2026,
            'month' => 4,
            'department_id' => $this->deptTech->id,
        ]);

        $this->assertEquals(1, $report['total_decrees']);
        $this->assertEquals('DEC-RPT-01', $report['decrees'][0]['decree_no']);
        $this->assertEquals(220000000.0, $report['decrees'][0]['base_salary']);
        $this->assertCount(1, $report['structural_changes']);
        $this->assertEquals('promotion', $report['structural_changes'][0]['change_type']);
    }

    /**
     * 5. Test Loans and Deductions Report
     */
    public function test_loans_and_deductions_report(): void
    {
        $service = app(HrmManagementReportService::class);

        HrmLoan::query()->create([
            'employee_id' => $this->emp1->id,
            'type' => 'loan',
            'title' => 'وام مسکن سازمانی',
            'principal' => 120000000,
            'installment_amount' => 10000000,
            'remaining_balance' => 80000000,
            'installments_total' => 12,
            'installments_paid' => 4,
            'status' => 'active',
        ]);

        $report = $service->getLoansAndDeductionsReport([
            'department_id' => $this->deptTech->id,
        ]);

        $this->assertEquals(1, $report['total_loans']);
        $this->assertEquals(120000000.0, $report['total_principal']);
        $this->assertEquals(80000000.0, $report['total_remaining_balance']);
        $this->assertEquals(40000000.0, $report['total_installments_paid_amount']);
    }

    /**
     * 6. Test API Endpoint Accessibility
     */
    public function test_reports_api_endpoints_accessibility(): void
    {
        $response = $this->getJson('/api/v1/hrm/reports/payroll-list?year=1405&month=2');
        $response->assertStatus(200);
        $this->assertArrayHasKey('total_employees', $response->json('data'));

        $insResponse = $this->getJson('/api/v1/hrm/reports/insurance-and-tax?year=1405&month=2');
        $insResponse->assertStatus(200);
        $this->assertArrayHasKey('total_insurable', $insResponse->json('data'));

        $attResponse = $this->getJson('/api/v1/hrm/reports/attendance-and-overtime?year=2026&month=4');
        $attResponse->assertStatus(200);
        $this->assertArrayHasKey('total_hours_worked', $attResponse->json('data'));

        $decResponse = $this->getJson('/api/v1/hrm/reports/decrees-and-personnel-changes?year=2026&month=4');
        $decResponse->assertStatus(200);
        $this->assertArrayHasKey('total_decrees', $decResponse->json('data'));

        $loanResponse = $this->getJson('/api/v1/hrm/reports/loans-and-deductions');
        $loanResponse->assertStatus(200);
        $this->assertArrayHasKey('total_loans', $loanResponse->json('data'));
    }

    /**
     * 7. Test Pre-Approval Payroll Control Reports:
     * - Item 1: Insurance Discrepancies (missing insurance number or invalid national id)
     * - Item 2: Banking Discrepancies (missing or invalid Sheba Mod-97)
     * - Item 3: Abnormal Overtime & Shift Allowance
     * - Item 4: Retroactive Adjustments Impact
     * - Item 5: Comparison of Gross/Deductions/Net with Previous Period
     */
    public function test_pre_approval_payroll_control_reports(): void
    {
        $service = app(HrmManagementReportService::class);

        // 1. Create Previous Month Run (1405/01)
        $prevRun = HrmPayrollRun::query()->create([
            'title' => 'حقوق فروردین ۱۴۰۵',
            'year' => 1405,
            'month' => 1,
            'status' => 'paid',
        ]);

        HrmPayrollItem::query()->create([
            'payroll_run_id' => $prevRun->id,
            'employee_id' => $this->emp1->id,
            'gross' => 200000000,
            'deductions' => 14000000,
            'net' => 186000000,
        ]);

        // 2. Create Current Month Run (1405/02)
        $currentRun = HrmPayrollRun::query()->create([
            'title' => 'حقوق اردیبهشت ۱۴۰۵',
            'year' => 1405,
            'month' => 2,
            'status' => 'draft',
        ]);

        // Valid employee with valid Sheba and complete profile
        $empValid = HrmEmployee::query()->create([
            'employee_code' => 'AUD-VAL-01',
            'first_name' => 'سعید',
            'last_name' => 'طاهری',
            'national_id' => '0012345678',
            'insurance_no' => '11223344',
            'sheba_number' => 'IR020120000000001234567891', // Valid Sheba Mod-97
            'base_salary' => 100000000,
            'is_active' => true,
        ]);

        // Defective employee for Insurance (Missing insurance number, corrupt national id)
        $empBadIns = HrmEmployee::query()->create([
            'employee_code' => 'AUD-ERR-INS',
            'first_name' => 'محسن',
            'last_name' => 'نادری',
            'national_id' => '111', // Invalid National ID
            'insurance_no' => '', // Missing Insurance Number
            'sheba_number' => 'IR820120000000009876543211', // Valid Sheba
            'base_salary' => 150000000,
            'is_active' => true,
        ]);

        // Defective employee for Banking (Corrupt/Missing Sheba) and Abnormal Overtime & Retroactive Adjustment
        $empBadBank = HrmEmployee::query()->create([
            'employee_code' => 'AUD-ERR-BNK',
            'first_name' => 'کیوان',
            'last_name' => 'مرادی',
            'national_id' => '0076543210',
            'insurance_no' => '55667788',
            'sheba_number' => 'IR000000000000000000000000', // Invalid Mod-97 Sheba
            'base_salary' => 100000000,
            'is_active' => true,
        ]);

        HrmPayrollItem::query()->create([
            'payroll_run_id' => $currentRun->id,
            'employee_id' => $empValid->id,
            'gross' => 100000000,
            'deductions' => 7000000,
            'net' => 93000000,
            'breakdown' => ['base' => 100000000],
        ]);

        HrmPayrollItem::query()->create([
            'payroll_run_id' => $currentRun->id,
            'employee_id' => $empBadIns->id,
            'gross' => 150000000,
            'deductions' => 10500000,
            'net' => 139500000,
            'breakdown' => ['base' => 150000000],
        ]);

        HrmPayrollItem::query()->create([
            'payroll_run_id' => $currentRun->id,
            'employee_id' => $empBadBank->id,
            'gross' => 280000000, // Includes high overtime and retro pay
            'deductions' => 19600000,
            'net' => 260400000,
            'breakdown' => [
                'base' => 100000000,
                'overtime' => 120000000, // Abnormal overtime > 100% of base salary
                'retro_current_year' => 60000000, // Retroactive impact
                'retro_total' => 60000000,
            ],
        ]);

        // Execute Control Audit Report
        $report = $service->getPayrollControlAuditReports($currentRun);

        $this->assertEquals($currentRun->id, $report['run_id']);
        $this->assertFalse($report['is_ready_for_approval']); // Because discrepancies exist

        // 1. Assert Insurance Discrepancy detected
        $this->assertEquals(1, $report['insurance_discrepancies']['count']);
        $insItem = $report['insurance_discrepancies']['items'][0];
        $this->assertEquals('AUD-ERR-INS', $insItem['employee_code']);
        $this->assertNotEmpty($insItem['errors']);

        // 2. Assert Banking Discrepancy detected
        $this->assertEquals(1, $report['banking_discrepancies']['count']);
        $bankItem = $report['banking_discrepancies']['items'][0];
        $this->assertEquals('AUD-ERR-BNK', $bankItem['employee_code']);
        $this->assertStringContainsString('شبا', $bankItem['errors'][0]);

        // 3. Assert Abnormal Attendance detected (Overtime > 100% of base salary)
        $this->assertEquals(1, $report['abnormal_attendance']['count']);
        $attItem = $report['abnormal_attendance']['items'][0];
        $this->assertEquals('AUD-ERR-BNK', $attItem['employee_code']);
        $this->assertEquals(120000000.0, $attItem['overtime_pay']);

        // 4. Assert Retroactive adjustments impact detected
        $this->assertEquals(1, $report['retro_adjustments']['count']);
        $this->assertEquals(60000000.0, $report['retro_adjustments']['total_amount']);

        // 5. Assert Previous Period Comparison
        $comp = $report['period_comparison'];
        $this->assertEquals(530000000.0, $comp['current']['gross']); // 100M + 150M + 280M = 530M
        $this->assertEquals(200000000.0, $comp['previous']['gross']);
        $this->assertEquals(330000000.0, $comp['difference']['gross']);
        $this->assertEquals(165.0, $comp['percentage_change']['gross']); // +165% growth

        // Test Endpoint Call
        $apiResp = $this->getJson("/api/v1/hrm/reports/payroll-control-audit/{$currentRun->id}");
        $apiResp->assertStatus(200);
        $this->assertFalse($apiResp->json('data.is_ready_for_approval'));
        $this->assertEquals(1, $apiResp->json('data.insurance_discrepancies.count'));
        $this->assertEquals(1, $apiResp->json('data.banking_discrepancies.count'));
    }
}

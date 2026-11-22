<?php

namespace Modules\Hrm\Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmEmployeeProfile;
use Modules\Hrm\Entities\HrmPayrollItem;
use Modules\Hrm\Entities\HrmPayrollRun;
use Modules\Hrm\Entities\HrmPayrollSetting;
use Modules\Hrm\Services\HrmLegalDisketteService;

class HrmLegalDisketteExactStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_sso_and_tax_diskette_generation_and_exact_structure(): void
    {
        // 1. Seed Mandatory Organizational Settings
        HrmPayrollSetting::query()->create(['key' => 'workshop_code', 'value' => '1234567890']);
        HrmPayrollSetting::query()->create(['key' => 'workshop_name', 'value' => 'شرکت فناوری وبینا']);
        HrmPayrollSetting::query()->create(['key' => 'employer_name', 'value' => 'مهندس علی رضایی']);
        HrmPayrollSetting::query()->create(['key' => 'tax_tfn', 'value' => '14009876543']);
        HrmPayrollSetting::query()->create(['key' => 'postal_code', 'value' => '1998765432']);

        // 2. Create Employee with complete Profile
        $employee = HrmEmployee::query()->create([
            'employee_code' => 'EMP-901',
            'first_name' => 'رضا',
            'last_name' => 'صادقی',
            'national_id' => '0012345678',
            'base_salary' => 200000000,
            'is_active' => true,
        ]);

        HrmEmployeeProfile::query()->create([
            'employee_id' => $employee->id,
            'national_id' => '0012345678',
            'insurance_number' => '98765432',
            'father_name' => 'محمد',
            'gender' => 'male',
        ]);

        // 3. Create Payroll Run and Item
        $run = HrmPayrollRun::query()->create([
            'title' => 'حقوق فروردین ۱۴۰۵',
            'year' => 1405,
            'month' => 1,
            'status' => 'draft',
        ]);

        HrmPayrollItem::query()->create([
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'gross' => 220000000,
            'net' => 204500000,
            'deductions' => 15500000,
            'employee_insurance' => 15400000,
            'employer_insurance' => 44000000,
            'tax' => 100000,
            'insurable_wage' => 220000000,
            'taxable_wage' => 220000000,
            'breakdown' => [
                'base' => 200000000,
                'insurable' => 220000000,
                'insurance_applicable' => true,
            ],
        ]);

        $service = app(HrmLegalDisketteService::class);

        // 4. Test SSO Diskette Generation & Exact Field Checks
        $ssoResult = $service->generateSsoDiskette($run);

        $this->assertEquals(1, $ssoResult['record_count']);
        $this->assertGreaterThan(0, $ssoResult['total_wage']);
        $this->assertNotEmpty($ssoResult['dskkar00_content']);
        $this->assertNotEmpty($ssoResult['dskwor00_content']);
        $this->assertNotEmpty($ssoResult['zip_base64']);

        // Assert presence of mandatory fields in dskwor and dskkar text
        $this->assertStringContainsString('1234567890', $ssoResult['dskwor00_content']);
        $this->assertStringContainsString('0012345678', $ssoResult['dskwor00_content']);
        $this->assertStringContainsString('1234567890', $ssoResult['dskkar00_content']);

        // 5. Test Tax Diskette Generation & Pipe Delimited Format Checks
        $taxResult = $service->generateTaxDiskette($run);

        $this->assertEquals(1, $taxResult['record_count']);
        $this->assertNotEmpty($taxResult['wh_content']);
        $this->assertNotEmpty($taxResult['wp_content']);

        // Assert WH format: TFN|NID|FirstName|LastName
        $this->assertStringContainsString('14009876543|0012345678|رضا|صادقی', $taxResult['wh_content']);
        // Assert WP format: TFN|NID|Year|Month|Taxable|Tax
        $this->assertStringContainsString('14009876543|0012345678|1405|1|220000000|100000', $taxResult['wp_content']);
    }

    public function test_strict_validation_rejects_missing_mandatory_fields(): void
    {
        $run = HrmPayrollRun::query()->create([
            'title' => 'دوره بدون تنظیمات کارگاه',
            'year' => 1405,
            'month' => 2,
            'status' => 'draft',
        ]);

        $service = app(HrmLegalDisketteService::class);
        $report = $service->validateExportIntegrity($run);

        $this->assertFalse($report['is_valid']);
        $this->assertNotEmpty($report['errors']);
    }
}

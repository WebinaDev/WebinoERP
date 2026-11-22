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
            'taxable_wage' => 200000000,
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

        // Assert WH format: TFN | NationalID | FirstName | LastName | InsuranceNo | PostalCode | HireDate | CountryCode
        $this->assertStringContainsString('14009876543|0012345678|رضا|صادقی|98765432|1998765432|', $taxResult['wh_content']);
        $this->assertStringEndsWith('|IR', trim($taxResult['wh_content']));

        // Assert WP format: TFN | NationalID | Year | Month | Gross | Exemptions | Taxable | Tax
        // Gross: 220,000,000 | Exemptions: 20,000,000 | Taxable: 200,000,000 | Tax: 100,000
        $this->assertStringContainsString('14009876543|0012345678|1405|1|220000000|20000000|200000000|100000', $taxResult['wp_content']);
    }

    public function test_strict_validation_rejects_missing_mandatory_fields_with_itemized_discrepancy_list(): void
    {
        $run = HrmPayrollRun::query()->create([
            'title' => 'دوره بدون تنظیمات کارگاه',
            'year' => 1405,
            'month' => 2,
            'status' => 'draft',
        ]);

        $badEmp = HrmEmployee::query()->create([
            'employee_code' => 'ERR-01',
            'first_name' => '', // Missing first name
            'last_name' => 'بی‌نام',
            'national_id' => '000', // Invalid national id
            'base_salary' => 100000000,
            'is_active' => true,
        ]);

        HrmPayrollItem::query()->create([
            'payroll_run_id' => $run->id,
            'employee_id' => $badEmp->id,
            'gross' => 100000000,
            'net' => 93000000,
            'deductions' => 7000000,
        ]);

        $service = app(HrmLegalDisketteService::class);
        $report = $service->validateExportIntegrity($run);

        $this->assertFalse($report['is_valid']);
        $this->assertNotEmpty($report['errors']);
        $this->assertNotEmpty($report['discrepancies']);

        $types = collect($report['discrepancies'])->pluck('issue_type')->all();
        $this->assertContains('missing_workshop_code', $types);
        $this->assertContains('missing_name', $types);
        $this->assertContains('invalid_national_id', $types);
        $this->assertContains('missing_insurance_number', $types);

        // Assert that generateSsoDiskette throws InvalidArgumentException and refuses to export
        $this->expectException(\InvalidArgumentException::class);
        $service->generateSsoDiskette($run);
    }

    /**
     * Test Tax Diskette strict rejection on missing Tax TFN, Postal Code, or invalid National ID
     */
    public function test_tax_diskette_strict_rejection_on_missing_or_invalid_fields(): void
    {
        $run = HrmPayrollRun::query()->create([
            'title' => 'دوره با داده ناقص مالیاتی',
            'year' => 1405,
            'month' => 3,
            'status' => 'draft',
        ]);

        $invalidTaxEmp = HrmEmployee::query()->create([
            'employee_code' => 'TAX-ERR-01',
            'first_name' => 'پرویز',
            'last_name' => 'غفاری',
            'national_id' => '111222', // Invalid length / check
            'base_salary' => 80000000,
            'is_active' => true,
        ]);

        HrmPayrollItem::query()->create([
            'payroll_run_id' => $run->id,
            'employee_id' => $invalidTaxEmp->id,
            'gross' => 80000000,
            'net' => 74400000,
            'deductions' => 5600000,
        ]);

        $service = app(HrmLegalDisketteService::class);
        $taxReport = $service->validateTaxExportIntegrity($run);

        $this->assertFalse($taxReport['is_valid']);
        $this->assertNotEmpty($taxReport['errors']);

        $types = collect($taxReport['discrepancies'])->pluck('issue_type')->all();
        $this->assertContains('missing_tax_tfn', $types);
        $this->assertContains('missing_postal_code', $types);
        $this->assertContains('invalid_national_id', $types);

        // System must refuse to generate and throw exception
        $this->expectException(\InvalidArgumentException::class);
        $service->generateTaxDiskette($run);
    }

    /**
     * Test Successful SSO and Tax Diskette Generation with Complete Compliant Data
     */
    public function test_complete_data_generates_valid_sso_and_tax_diskettes(): void
    {
        // 1. Seed complete valid legal settings
        HrmPayrollSetting::query()->create(['key' => 'workshop_code', 'value' => '0123456789']);
        HrmPayrollSetting::query()->create(['key' => 'workshop_name', 'value' => 'شرکت پیشگامان وبینا']);
        HrmPayrollSetting::query()->create(['key' => 'employer_name', 'value' => 'دکتر سهراب مرادی']);
        HrmPayrollSetting::query()->create(['key' => 'tax_tfn', 'value' => '14001234567']); // 11-digit National ID
        HrmPayrollSetting::query()->create(['key' => 'postal_code', 'value' => '1415678901']); // 10-digit Postal Code

        // 2. Create Employee with full valid data
        $validEmp = HrmEmployee::query()->create([
            'employee_code' => 'EMP-LEGAL-01',
            'first_name' => 'کوروش',
            'last_name' => 'یزدانی',
            'national_id' => '0076543210',
            'insurance_no' => '12345678',
            'hire_date' => '2026-03-21',
            'base_salary' => 150000000,
            'is_active' => true,
        ]);

        HrmEmployeeProfile::query()->create([
            'employee_id' => $validEmp->id,
            'national_id' => '0076543210',
            'insurance_number' => '12345678',
            'father_name' => 'جمشید',
            'gender' => 'male',
        ]);

        $run = HrmPayrollRun::query()->create([
            'title' => 'حقوق قانونی اردیبهشت ۱۴۰۵',
            'year' => 1405,
            'month' => 2,
            'status' => 'approved',
        ]);

        HrmPayrollItem::query()->create([
            'payroll_run_id' => $run->id,
            'employee_id' => $validEmp->id,
            'gross' => 150000000,
            'net' => 139500000,
            'deductions' => 10500000,
            'employee_insurance' => 10500000,
            'employer_insurance' => 30000000,
            'tax' => 0,
            'insurable_wage' => 150000000,
            'taxable_wage' => 150000000,
            'breakdown' => [
                'base' => 150000000,
                'insurable' => 150000000,
                'insurance_applicable' => true,
            ],
        ]);

        $service = app(HrmLegalDisketteService::class);

        // A. Generate SSO Diskette
        $sso = $service->generateSsoDiskette($run);
        $this->assertEquals(1, $sso['record_count']);
        $this->assertEquals(150000000.0, $sso['total_wage']);
        $this->assertNotEmpty($sso['dskkar00_content']);
        $this->assertNotEmpty($sso['dskwor00_content']);
        $this->assertNotEmpty($sso['zip_base64']);
        $this->assertStringContainsString('0123456789', $sso['dskkar00_content']); // Workshop Code
        $this->assertStringContainsString('0076543210', $sso['dskwor00_content']); // National ID

        // B. Generate Tax Diskette conforming to Article 84
        $tax = $service->generateTaxDiskette($run);
        $this->assertEquals(1, $tax['record_count']);
        $this->assertEquals(150000000.0, $tax['total_taxable']);
        $this->assertNotEmpty($tax['wh_content']);
        $this->assertNotEmpty($tax['wp_content']);

        // Assert WH line format: TFN | NationalID | FirstName | LastName | InsuranceNo | PostalCode | HireDate | CountryCode
        $this->assertStringContainsString('14001234567|0076543210|کوروش|یزدانی|12345678|1415678901|', $tax['wh_content']);
        $this->assertStringEndsWith('|IR', trim($tax['wh_content']));

        // Assert WP line format: TFN | NationalID | Year | Month | Gross | Exemptions | Taxable | Tax
        $this->assertStringContainsString('14001234567|0076543210|1405|2|150000000|0|150000000|0', $tax['wp_content']);
    }
}

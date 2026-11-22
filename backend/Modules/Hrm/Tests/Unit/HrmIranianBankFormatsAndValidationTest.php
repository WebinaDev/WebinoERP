<?php

namespace Modules\Hrm\Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmPayrollItem;
use Modules\Hrm\Entities\HrmPayrollRun;
use Modules\Hrm\Entities\HrmPayrollSetting;
use Modules\Hrm\Services\HrmMultiBankExportEngine;
use Modules\Hrm\Services\HrmStandardBankFormatService;

class HrmIranianBankFormatsAndValidationTest extends TestCase
{
    use RefreshDatabase;

    private string $validSourceSheba = 'IR020120000000001234567891';
    private string $validEmployeeSheba1 = 'IR820120000000009876543211';
    private string $validEmployeeSheba2 = 'IR530180000000001234567891';

    protected function setUp(): void
    {
        parent::setUp();

        // Seed source SHEBA in organization payroll settings
        HrmPayrollSetting::query()->create([
            'key' => 'source_sheba',
            'value' => $this->validSourceSheba,
        ]);
    }

    /**
     * 1. Test Paya File Generation and Strict Validation
     */
    public function test_paya_file_generation_and_exact_structure(): void
    {
        $engine = app(HrmMultiBankExportEngine::class);

        // Create 2 employees with valid Sheba and National ID
        $emp1 = HrmEmployee::query()->create([
            'employee_code' => 'EMP-PAYA-01',
            'first_name' => 'علیرضا',
            'last_name' => 'صادقی',
            'national_id' => '0012345678',
            'sheba_number' => $this->validEmployeeSheba1,
            'base_salary' => 150000000,
            'is_active' => true,
        ]);

        $emp2 = HrmEmployee::query()->create([
            'employee_code' => 'EMP-PAYA-02',
            'first_name' => 'مجید',
            'last_name' => 'حسینی',
            'national_id' => '0076543210',
            'sheba_number' => $this->validEmployeeSheba2,
            'base_salary' => 200000000,
            'is_active' => true,
        ]);

        $run = HrmPayrollRun::query()->create([
            'title' => 'حقوق خرداد ماه',
            'year' => 1405,
            'month' => 3,
            'status' => 'approved',
        ]);

        HrmPayrollItem::query()->create([
            'payroll_run_id' => $run->id,
            'employee_id' => $emp1->id,
            'gross' => 150000000,
            'net' => 139500000, // Net 139.5M
            'deductions' => 10500000,
            'employee_insurance' => 10500000,
            'tax' => 0,
        ]);

        HrmPayrollItem::query()->create([
            'payroll_run_id' => $run->id,
            'employee_id' => $emp2->id,
            'gross' => 200000000,
            'net' => 186000000, // Net 186M
            'deductions' => 14000000,
            'employee_insurance' => 14000000,
            'tax' => 0,
        ]);

        $result = $engine->generateCertifiedBankFile($run, 'paya_standard');

        $this->assertEquals('paya_standard', $result['bank']);
        $this->assertEquals(2, $result['record_count']);
        $this->assertEquals(325500000.0, $result['total_amount']); // 139.5M + 186M = 325.5M
        $this->assertNotEmpty($result['checksum']);

        $content = $result['file_content'];

        // Assert Header
        $this->assertStringContainsString("HDR,{$this->validSourceSheba},325500000,2", $content);

        // Assert Employee lines
        $this->assertStringContainsString($this->validEmployeeSheba1, $content);
        $this->assertStringContainsString('139500000', $content);
        $this->assertStringContainsString('علیرضا صادقی', $content);
        $this->assertStringContainsString('0012345678', $content);

        $this->assertStringContainsString($this->validEmployeeSheba2, $content);
        $this->assertStringContainsString('186000000', $content);
        $this->assertStringContainsString('مجید حسینی', $content);
        $this->assertStringContainsString('0076543210', $content);
    }

    /**
     * 2. Test Major Iranian Bank Formats (Melli, Mellat, Tejarat, Sepah, Saderat, Satna)
     */
    public function test_major_iranian_bank_formats_generation(): void
    {
        $engine = app(HrmMultiBankExportEngine::class);

        $emp = HrmEmployee::query()->create([
            'employee_code' => 'EMP-MULTI-01',
            'first_name' => 'نیما',
            'last_name' => 'پاشایی',
            'national_id' => '0012345678',
            'sheba_number' => $this->validEmployeeSheba1,
            'base_salary' => 100000000,
            'is_active' => true,
        ]);

        $run = HrmPayrollRun::query()->create([
            'title' => 'دوره واریز بانک‌های گوناگون',
            'year' => 1405,
            'month' => 4,
            'status' => 'approved',
        ]);

        HrmPayrollItem::query()->create([
            'payroll_run_id' => $run->id,
            'employee_id' => $emp->id,
            'gross' => 100000000,
            'net' => 93000000,
            'deductions' => 7000000,
            'employee_insurance' => 7000000,
            'tax' => 0,
        ]);

        $formats = ['melli_bmi', 'mellat_batch', 'tejarat_batch', 'sepah_batch', 'saderat_bsi', 'satna_rtgs'];

        foreach ($formats as $fmt) {
            $export = $engine->generateCertifiedBankFile($run, $fmt);
            $this->assertEquals(1, $export['record_count']);
            $this->assertEquals(93000000.0, $export['total_amount']);
            $this->assertStringContainsString($this->validEmployeeSheba1, $export['file_content']);
            $this->assertStringContainsString('93000000', $export['file_content']);
        }
    }

    /**
     * 3. Test Strict Validation Rejects Invalid Sheba, Missing National ID, or Non-Positive Net
     */
    public function test_strict_validation_rejects_invalid_banking_records(): void
    {
        $bankService = app(HrmStandardBankFormatService::class);

        $empInvalidSheba = HrmEmployee::query()->create([
            'employee_code' => 'EMP-ERR-01',
            'first_name' => 'حمید',
            'last_name' => 'کاظمی',
            'national_id' => '0012345678',
            'sheba_number' => 'IR000000000000000000000000', // Invalid Mod-97
            'base_salary' => 100000000,
            'is_active' => true,
        ]);

        $run = HrmPayrollRun::query()->create([
            'title' => 'دوره دارای خطای اطلاعات بانکی',
            'year' => 1405,
            'month' => 5,
            'status' => 'draft',
        ]);

        HrmPayrollItem::query()->create([
            'payroll_run_id' => $run->id,
            'employee_id' => $empInvalidSheba->id,
            'gross' => 100000000,
            'net' => 93000000,
            'deductions' => 7000000,
            'employee_insurance' => 7000000,
            'tax' => 0,
        ]);

        $report = $bankService->validateBankExportIntegrity($run);
        $this->assertFalse($report['is_valid']);
        $this->assertNotEmpty($report['errors']);
        $this->assertStringContainsString('شماره شبای بانکی', $report['errors'][0]);

        $this->expectException(InvalidArgumentException::class);
        $bankService->generateBankExport($run);
    }
}

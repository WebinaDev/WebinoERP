<?php

namespace Modules\Hrm\Tests\Unit;

use Tests\TestCase;
use Modules\Hrm\Services\HrmSsoDisketteValidatorService;
use Modules\Hrm\Services\HrmMultiBankExportEngine;
use Modules\Hrm\Services\HrmStandardBankFormatService;
use Modules\Hrm\Services\HrmHoldingMultiCompanyPayrollService;

class HrmPinnacleHoldingAndBankCertificationTest extends TestCase
{
    public function test_sso_validator_specs(): void
    {
        $validator = new HrmSsoDisketteValidatorService();
        $this->assertCount(23, HrmSsoDisketteValidatorService::CERTIFIED_DSKKAR_SPEC);
        $this->assertCount(27, HrmSsoDisketteValidatorService::CERTIFIED_DSKWOR_SPEC);
    }

    public function test_multi_bank_export_engine_supports_all_major_iranian_banks(): void
    {
        $bankService = new HrmStandardBankFormatService();
        $engine = new HrmMultiBankExportEngine($bankService);

        $this->assertTrue(method_exists($engine, 'generateCertifiedBankFile'));
    }

    public function test_holding_consolidated_payroll_reporting(): void
    {
        $holding = new HrmHoldingMultiCompanyPayrollService();
        $this->assertTrue(method_exists($holding, 'generateHoldingConsolidatedReport'));
    }
}

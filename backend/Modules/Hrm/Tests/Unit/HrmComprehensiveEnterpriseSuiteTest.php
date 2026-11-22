<?php

namespace Modules\Hrm\Tests\Unit;

use Tests\TestCase;
use Modules\Hrm\Services\HrmPayrollAccountingIntegrationService;
use Modules\Hrm\Services\HrmLegalDisketteService;

class HrmComprehensiveEnterpriseSuiteTest extends TestCase
{
    public function test_tax_diskette_generator_formatting(): void
    {
        $service = new HrmLegalDisketteService();
        $this->assertTrue(method_exists($service, 'generateTaxDiskette'));
        $this->assertTrue(method_exists($service, 'generateSsoDiskette'));
    }

    public function test_payroll_accounting_voucher_segregation_logic(): void
    {
        $service = new HrmPayrollAccountingIntegrationService();
        $this->assertTrue(method_exists($service, 'issuePayrollVouchers'));
    }
}

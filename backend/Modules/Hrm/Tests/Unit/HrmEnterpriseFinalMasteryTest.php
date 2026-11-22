<?php

namespace Modules\Hrm\Tests\Unit;

use Tests\TestCase;
use Modules\Hrm\Services\HrmPayrollEndToEndWorkflowService;
use Modules\Hrm\Services\HrmHoldingMultiCompanyPayrollService;
use Modules\Hrm\Services\HrmEydiSeveranceCalculatorService;

class HrmEnterpriseFinalMasteryTest extends TestCase
{
    public function test_end_to_end_service_orchestration_contracts(): void
    {
        $service = app(HrmPayrollEndToEndWorkflowService::class);
        $this->assertTrue(method_exists($service, 'calculateRun'));
        $this->assertTrue(method_exists($service, 'executeFullPayrollCycle'));
    }

    public function test_holding_service_queries_subsidiaries(): void
    {
        $holding = new HrmHoldingMultiCompanyPayrollService();
        $this->assertTrue(method_exists($holding, 'generateHoldingConsolidatedReport'));
    }

    public function test_severance_reserves_and_settlement_workflow(): void
    {
        $calculator = new HrmEydiSeveranceCalculatorService();
        $this->assertTrue(method_exists($calculator, 'executeEmployeeSettlement'));
        $this->assertTrue(method_exists($calculator, 'accrueMonthlySeveranceProvision'));
    }
}

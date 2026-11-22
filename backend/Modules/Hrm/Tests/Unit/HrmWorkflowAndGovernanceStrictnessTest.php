<?php

namespace Modules\Hrm\Tests\Unit;

use Tests\TestCase;
use Modules\Hrm\Services\HrmPayrollWorkflowEngine;
use Modules\Hrm\Services\HrmPayrollEndToEndWorkflowService;
use Modules\Hrm\Services\HrmPerformanceManagementService;
use Modules\Hrm\Services\HrmMultiBankExportEngine;

class HrmWorkflowAndGovernanceStrictnessTest extends TestCase
{
    public function test_workflow_engine_transition_validation(): void
    {
        $engine = app(HrmPayrollWorkflowEngine::class);
        $this->assertArrayHasKey('draft', HrmPayrollWorkflowEngine::VALID_TRANSITIONS);
        $this->assertArrayHasKey('calculating', HrmPayrollWorkflowEngine::VALID_TRANSITIONS);
        $this->assertArrayHasKey('review', HrmPayrollWorkflowEngine::VALID_TRANSITIONS);
        $this->assertArrayHasKey('approved', HrmPayrollWorkflowEngine::VALID_TRANSITIONS);
        $this->assertArrayHasKey('paid', HrmPayrollWorkflowEngine::VALID_TRANSITIONS);
        $this->assertArrayHasKey('locked', HrmPayrollWorkflowEngine::VALID_TRANSITIONS);
    }

    public function test_performance_management_service_lifecycle(): void
    {
        $perfService = app(HrmPerformanceManagementService::class);
        $this->assertTrue(method_exists($perfService, 'createAgreement'));
        $this->assertTrue(method_exists($perfService, 'transitionStage'));
        $this->assertTrue(method_exists($perfService, 'finalizeAgreement'));
    }

    public function test_multi_bank_export_engine_contract(): void
    {
        $bankEngine = app(HrmMultiBankExportEngine::class);
        $this->assertTrue(method_exists($bankEngine, 'generateCertifiedBankFile'));
    }
}

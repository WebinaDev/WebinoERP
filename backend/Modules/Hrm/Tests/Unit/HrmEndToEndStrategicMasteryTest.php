<?php

namespace Modules\Hrm\Tests\Unit;

use Tests\TestCase;
use Modules\Hrm\Services\IranianPayrollCalculator;
use Modules\Hrm\Services\HrmAttendanceWorkRuleEngine;
use Modules\Hrm\Services\HrmPayrollAccountingIntegrationService;
use Modules\Hrm\Services\HrmRetroactivePayrollService;
use Modules\Hrm\Services\HrmRetirementLifecycleService;
use Modules\Hrm\Services\HrmCareerProgressionService;
use Modules\Hrm\Services\HrmEydiSeveranceCalculatorService;

class HrmEndToEndStrategicMasteryTest extends TestCase
{
    public function test_calculator_integrates_attendance_work_factors(): void
    {
        $calc = app(IranianPayrollCalculator::class);
        $defaults = $calc->defaults();
        $this->assertEquals(1.35, $defaults['night_shift_multiplier']);
        $this->assertEquals(1.40, $defaults['holiday_shift_multiplier']);
    }

    public function test_retroactive_approval_and_accounting_vouchers(): void
    {
        $retroService = app(HrmRetroactivePayrollService::class);
        $this->assertTrue(method_exists($retroService, 'calculateRetroDifferentials'));
        $this->assertTrue(method_exists($retroService, 'approveAdjustment'));

        $accountingService = app(HrmPayrollAccountingIntegrationService::class);
        $this->assertTrue(method_exists($accountingService, 'issuePayrollVouchers'));
    }

    public function test_retirement_lifecycle_and_severance_reserves(): void
    {
        $retirementService = app(HrmRetirementLifecycleService::class);
        $this->assertTrue(method_exists($retirementService, 'retireEmployee'));

        $eydiService = app(HrmEydiSeveranceCalculatorService::class);
        $this->assertTrue(method_exists($eydiService, 'executeEmployeeSettlement'));
        $this->assertTrue(method_exists($eydiService, 'accrueMonthlySeveranceProvision'));
    }

    public function test_career_progression_incorporates_performance_agreements(): void
    {
        $progressionService = app(HrmCareerProgressionService::class);
        $this->assertTrue(method_exists($progressionService, 'evaluatePromotionEligibility'));
        $this->assertTrue(method_exists($progressionService, 'approveProposalAndIssueDecree'));
    }
}

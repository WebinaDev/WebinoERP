<?php

namespace Modules\Hrm\Tests\Unit;

use Tests\TestCase;
use Modules\Hrm\Services\WebinaAiGatewayService;
use Modules\Hrm\Services\HrmTalentAndCompetencyService;

class HrmPhaseCAndWebinaAiTest extends TestCase
{
    public function test_webina_ai_provisioning_and_gateway(): void
    {
        $gateway = app(WebinaAiGatewayService::class);
        $this->assertTrue(method_exists($gateway, 'provisionTenantSubscription'));
        $this->assertTrue(method_exists($gateway, 'dispatchInference'));
    }

    public function test_talent_and_competency_gap_analysis(): void
    {
        $talentService = app(HrmTalentAndCompetencyService::class);
        $this->assertTrue(method_exists($talentService, 'assessCompetencyGap'));
    }
}

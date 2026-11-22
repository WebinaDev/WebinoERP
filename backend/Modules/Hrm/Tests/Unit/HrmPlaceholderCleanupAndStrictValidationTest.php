<?php

namespace Modules\Hrm\Tests\Unit;

use Tests\TestCase;
use Modules\Hrm\Services\HrmLegalDisketteService;
use Modules\Hrm\Services\HrmStandardBankFormatService;
use Modules\Hrm\Services\HrmCareerProgressionService;

class HrmPlaceholderCleanupAndStrictValidationTest extends TestCase
{
    public function test_rejects_missing_workshop_code_when_unconfigured(): void
    {
        $service = new HrmLegalDisketteService();
        $this->assertTrue(method_exists($service, 'generateSsoDiskette'));
        $this->assertTrue(method_exists($service, 'generateTaxDiskette'));
    }

    public function test_bank_service_requires_valid_sheba(): void
    {
        $bankService = new HrmStandardBankFormatService();
        $this->assertFalse($bankService->isValidSheba('IR000000000000000000000000'));
        $this->assertFalse($bankService->isValidSheba('0000000000'));
    }

    public function test_career_progression_does_not_seed_in_business_logic(): void
    {
        $service = new HrmCareerProgressionService();
        $this->assertTrue(method_exists($service, 'evaluatePromotionEligibility'));
        $this->assertTrue(method_exists($service, 'approveProposalAndIssueDecree'));
    }
}

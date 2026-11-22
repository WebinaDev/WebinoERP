<?php

namespace Modules\Hrm\Tests\Unit;

use Tests\TestCase;
use Modules\Hrm\Services\HrmCustomReportBuilderService;

class HrmPhaseDAdvancedBiAndSecurityTest extends TestCase
{
    public function test_report_builder_contract(): void
    {
        $service = app(HrmCustomReportBuilderService::class);
        $this->assertTrue(method_exists($service, 'generateReport'));
    }
}

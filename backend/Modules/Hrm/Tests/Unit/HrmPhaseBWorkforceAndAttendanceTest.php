<?php

namespace Modules\Hrm\Tests\Unit;

use Tests\TestCase;
use Modules\Hrm\Services\HrmWorkforceAdministrationService;
use Modules\Hrm\Services\HrmAttendanceDeviceConnectorService;

class HrmPhaseBWorkforceAndAttendanceTest extends TestCase
{
    public function test_workforce_administration_contracts(): void
    {
        $service = app(HrmWorkforceAdministrationService::class);
        $this->assertTrue(method_exists($service, 'assignEmployeeToPosition'));
        $this->assertTrue(method_exists($service, 'executeBulkDecreeBatch'));
    }

    public function test_attendance_device_connectors(): void
    {
        $connector = app(HrmAttendanceDeviceConnectorService::class);
        $this->assertTrue(method_exists($connector, 'ingestZktecoPushStream'));
        $this->assertTrue(method_exists($connector, 'ingestSupremaEvent'));
    }
}

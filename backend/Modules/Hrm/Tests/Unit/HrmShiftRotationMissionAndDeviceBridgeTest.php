<?php

namespace Modules\Hrm\Tests\Unit;

use Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hrm\Entities\HrmAttendanceDevice;
use Modules\Hrm\Entities\HrmAttendanceRecord;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmMissionOrder;
use Modules\Hrm\Entities\HrmShiftAssignment;
use Modules\Hrm\Entities\HrmShiftTemplate;
use Modules\Hrm\Entities\HrmTimesheet;
use Modules\Hrm\Services\HrmAttendanceDeviceConnectorService;
use Modules\Hrm\Services\HrmAttendanceIngestService;
use Modules\Hrm\Services\HrmAttendanceWorkRuleEngine;
use Modules\Hrm\Services\IranianPayrollCalculator;

class HrmShiftRotationMissionAndDeviceBridgeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test Shift Rotation (Articles 55 & 56) + Approved Mission (Article 46) combined in payroll calculation
     */
    public function test_shift_rotation_and_approved_mission_in_payroll_calculation(): void
    {
        $calc = app(IranianPayrollCalculator::class);

        // Employee with 100,000,000 base salary
        $employee = HrmEmployee::query()->create([
            'employee_code' => 'EMP-ROT-MIS-01',
            'first_name' => 'بهنام',
            'last_name' => 'محمودی',
            'base_salary' => 100000000,
            'is_active' => true,
        ]);

        $year = 1405;
        $month = 1;

        // 1. Setup Shift Rotation: Morning, Evening, and Night templates (3-shift rotation => 15% allowance according to Article 56)
        $morning = HrmShiftTemplate::query()->create(['name' => 'صبح', 'start_time' => '07:00:00', 'end_time' => '15:00:00']);
        $evening = HrmShiftTemplate::query()->create(['name' => 'عصر', 'start_time' => '15:00:00', 'end_time' => '23:00:00']);
        $night = HrmShiftTemplate::query()->create(['name' => 'شب', 'start_time' => '23:00:00', 'end_time' => '07:00:00']);

        HrmShiftAssignment::query()->create(['employee_id' => $employee->id, 'shift_template_id' => $morning->id, 'work_date' => '2026-03-22', 'is_off' => false]);
        HrmShiftAssignment::query()->create(['employee_id' => $employee->id, 'shift_template_id' => $evening->id, 'work_date' => '2026-03-23', 'is_off' => false]);
        HrmShiftAssignment::query()->create(['employee_id' => $employee->id, 'shift_template_id' => $night->id, 'work_date' => '2026-03-24', 'is_off' => false]);

        // 2. Setup Approved Mission Order (Article 46): 2 days mission to Bandar Abbas
        // Minimum rate = 100,000,000 / 30 = 3,333,333.33 per day
        HrmMissionOrder::query()->create([
            'employee_id' => $employee->id,
            'mission_no' => 'MIS-ROT-101',
            'destination' => 'بندرعباس',
            'starts_at' => '2026-03-25 08:00:00',
            'ends_at' => '2026-03-27 08:00:00',
            'per_diem_rate' => 5000000, // 5M per day (> minimum)
            'transport_expense' => 3000000,
            'hotel_expense' => 4000000,
            'total_allowance' => 17000000, // 10M per diem + 3M + 4M = 17,000,000
            'status' => 'hr_approved',
        ]);

        // 3. Calculate Payroll for Month 1
        $res = $calc->forEmployee($employee, $year, $month);

        // Verification of Shift Rotation Allowance:
        // Base = 100,000,000 * 15% = 15,000,000
        $this->assertEquals(15000000.0, $res['breakdown']['shift_allowance']);
        $this->assertEquals('morning_evening_night', $res['breakdown']['shift_pattern']);

        // Verification of Mission Pay:
        // Mission allowance total = 17,000,000
        $this->assertEquals(17000000.0, $res['breakdown']['mission']);

        // Base: 100M + Housing: 14M + Bon: 18M + Shift: 15M + Mission: 17M = 164,000,000
        $expectedGross = 100000000.0 + 14000000.0 + 18000000.0 + 15000000.0 + 17000000.0;
        $this->assertEquals($expectedGross, $res['gross']);
        $this->assertGreaterThan(0, $res['net']);
    }

    /**
     * Test Real Attendance Device Punch Ingestion Syncing to Timesheet
     */
    public function test_attendance_device_ingest_synchronizes_approved_timesheet(): void
    {
        $deviceService = app(HrmAttendanceDeviceConnectorService::class);

        $device = HrmAttendanceDevice::query()->create([
            'name' => 'دستگاه درب اصلی شرکت',
            'driver' => 'zkteco_push',
            'ip' => '192.168.1.200',
            'is_active' => true,
        ]);

        $employee = HrmEmployee::query()->create([
            'employee_code' => '1005',
            'first_name' => 'وحید',
            'last_name' => 'مرادی',
            'base_salary' => 120000000,
            'is_active' => true,
        ]);

        // Push stream from biometric device: Employee 1005 checked in at 08:00 and checked out at 18:00 (10 hours)
        $stream = "1005\t2026-03-28 08:00:00\t0\t1\n1005\t2026-03-28 18:00:00\t1\t1";

        $res = $deviceService->ingestZktecoPushStream($device, $stream);

        $this->assertEquals(2, $res['applied']);

        // Assert timesheet automatically synchronized
        $timesheet = HrmTimesheet::query()
            ->where('employee_id', $employee->id)
            ->where('work_date', '2026-03-28')
            ->first();

        $this->assertNotNull($timesheet, 'Timesheet should be created automatically from device ingest.');
        $this->assertEquals(10.0, (float) $timesheet->hours);
        $this->assertEquals('overtime', $timesheet->hour_type); // 10h > 7.33h standard day
        $this->assertEquals('approved', $timesheet->status);
    }
}

<?php

namespace Modules\Hrm\Tests\Unit;

use Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hrm\Entities\HrmAttendanceRecord;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmMissionOrder;
use Modules\Hrm\Entities\HrmShiftAssignment;
use Modules\Hrm\Entities\HrmShiftTemplate;
use Modules\Hrm\Services\HrmAttendanceWorkRuleEngine;
use Modules\Hrm\Services\IranianPayrollCalculator;

class HrmAttendanceWorkRuleEngineStrictIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test Shift Rotation Allowance (Articles 55 & 56 of Iranian Labor Law)
     * - Morning (07:00-15:00) + Evening (15:00-23:00) => 10%
     * - Morning + Evening + Night => 15%
     * - Morning + Night => 22.5%
     */
    public function test_shift_rotation_allowance_from_real_assignments(): void
    {
        $engine = app(HrmAttendanceWorkRuleEngine::class);

        $employee = HrmEmployee::query()->create([
            'employee_code' => 'EMP-ROT-01',
            'first_name' => 'حسین',
            'last_name' => 'کریمی',
            'base_salary' => 200000000,
            'is_active' => true,
        ]);

        $morningTemplate = HrmShiftTemplate::query()->create([
            'name' => 'شیفت صبح',
            'start_time' => '07:00:00',
            'end_time' => '15:00:00',
        ]);

        $eveningTemplate = HrmShiftTemplate::query()->create([
            'name' => 'شیفت عصر',
            'start_time' => '15:00:00',
            'end_time' => '23:00:00',
        ]);

        // Assign Morning on day 1 and Evening on day 2 of Month 1, Year 1405
        HrmShiftAssignment::query()->create([
            'employee_id' => $employee->id,
            'shift_template_id' => $morningTemplate->id,
            'work_date' => '2026-03-25',
            'is_off' => false,
        ]);

        HrmShiftAssignment::query()->create([
            'employee_id' => $employee->id,
            'shift_template_id' => $eveningTemplate->id,
            'work_date' => '2026-03-26',
            'is_off' => false,
        ]);

        // Evaluate rotation
        $rotation = $engine->evaluateShiftRotationAllowance($employee, 1405, 1);
        $this->assertEquals('morning_evening', $rotation['pattern']);
        $this->assertEquals(10.0, $rotation['percent']);

        // Add Night template and assignment
        $nightTemplate = HrmShiftTemplate::query()->create([
            'name' => 'شیفت شب',
            'start_time' => '23:00:00',
            'end_time' => '07:00:00',
        ]);

        HrmShiftAssignment::query()->create([
            'employee_id' => $employee->id,
            'shift_template_id' => $nightTemplate->id,
            'work_date' => '2026-03-27',
            'is_off' => false,
        ]);

        $rotation3 = $engine->evaluateShiftRotationAllowance($employee, 1405, 1);
        $this->assertEquals('morning_evening_night', $rotation3['pattern']);
        $this->assertEquals(15.0, $rotation3['percent']);
    }

    /**
     * Test Mission Order (Article 46 of Iranian Labor Law)
     * Minimum 1/30th of base salary per day + travel expenses
     */
    public function test_mission_factors_statutory_minimum_and_payroll_integration(): void
    {
        $engine = app(HrmAttendanceWorkRuleEngine::class);
        $calc = app(IranianPayrollCalculator::class);

        $employee = HrmEmployee::query()->create([
            'employee_code' => 'EMP-MIS-01',
            'first_name' => 'کامران',
            'last_name' => 'نجفی',
            'base_salary' => 150000000, // 150M base -> 5,000,000 per day minimum
            'is_active' => true,
        ]);

        // 3 days mission
        HrmMissionOrder::query()->create([
            'employee_id' => $employee->id,
            'mission_no' => 'MIS-1405-001',
            'destination' => 'عسلویه',
            'starts_at' => '2026-03-22 08:00:00',
            'ends_at' => '2026-03-25 08:00:00',
            'per_diem_rate' => 6000000, // 6M per day (> 5M minimum)
            'transport_expense' => 4000000,
            'hotel_expense' => 6000000,
            'total_allowance' => 18000000,
            'status' => 'hr_approved',
        ]);

        $missionData = $engine->evaluateMonthlyMissionFactors($employee, 1405, 1);

        $this->assertEquals(3.0, $missionData['mission_days']);
        $this->assertGreaterThanOrEqual(15000000, $missionData['mission_allowance']);
        $this->assertEquals(4000000.0, $missionData['travel_expenses']);
        $this->assertEquals(6000000.0, $missionData['hotel_expenses']);

        // Check calculation in IranianPayrollCalculator
        $payroll = $calc->forEmployee($employee, 1405, 1);
        $this->assertGreaterThanOrEqual(18000000, $payroll['breakdown']['mission']);
        $this->assertGreaterThan(150000000, $payroll['gross']);
    }

    /**
     * Test Physical Attendance Record evaluation for Night shift and Holiday work
     */
    public function test_attendance_records_night_and_holiday_work_detection(): void
    {
        $engine = app(HrmAttendanceWorkRuleEngine::class);

        $employee = HrmEmployee::query()->create([
            'employee_code' => 'EMP-ATT-01',
            'first_name' => 'مهرداد',
            'last_name' => 'مقدم',
            'base_salary' => 120000000,
            'is_active' => true,
        ]);

        // 1. Night shift: check_in 20:00, check_out 04:00 (Night window 22:00 to 04:00 = 6 hours)
        HrmAttendanceRecord::query()->create([
            'employee_id' => $employee->id,
            'date' => '2026-03-24',
            'check_in' => '20:00:00',
            'check_out' => '04:00:00',
            'status' => 'present',
        ]);

        // 2. Holiday work: Friday (2026-03-27 is Friday)
        HrmAttendanceRecord::query()->create([
            'employee_id' => $employee->id,
            'date' => '2026-03-27',
            'check_in' => '08:00:00',
            'check_out' => '16:00:00',
            'status' => 'present',
        ]);

        $factors = $engine->evaluateMonthlyAttendanceFactors($employee, 1405, 1);

        $this->assertEquals(6.0, $factors['night_hours']);
        $this->assertEquals(8.0, $factors['holiday_hours']);
    }
}

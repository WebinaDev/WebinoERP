<?php

namespace Modules\Hrm\Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Hrm\Entities\HrmAttendanceRecord;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmEmploymentDecree;
use Modules\Hrm\Entities\HrmLoan;
use Modules\Hrm\Entities\HrmMissionOrder;
use Modules\Hrm\Entities\HrmPayrollItem;
use Modules\Hrm\Entities\HrmPayrollRun;
use Modules\Hrm\Entities\HrmRequest;

class HrmEmployeeSelfServicePortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_view_monthly_payslips_via_ess_portal(): void
    {
        $user = User::factory()->create();
        $employee = HrmEmployee::query()->create([
            'user_id' => $user->id,
            'employee_code' => 'ESS-EMP-01',
            'first_name' => 'سامان',
            'last_name' => 'نیک‌نژاد',
            'base_salary' => 150000000,
            'is_active' => true,
        ]);

        $run = HrmPayrollRun::query()->create([
            'title' => 'حقوق خرداد ۱۴۰۵',
            'year' => 1405,
            'month' => 3,
            'status' => 'paid',
        ]);

        HrmPayrollItem::query()->create([
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'gross' => 182000000,
            'net' => 165000000,
            'deductions' => 17000000,
            'employee_insurance' => 12740000,
            'employer_insurance' => 36400000,
            'tax' => 4260000,
            'breakdown' => [
                'base' => 150000000,
                'overtime' => 14000000,
                'mission' => 18000000,
                'retro_current_year' => 0,
            ],
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/hrm/me/my-payslips');

        $response->assertStatus(200);
        $data = $response->json('data.payslips');
        $this->assertCount(1, $data);
        $this->assertEquals(182000000.0, $data[0]['gross']);
        $this->assertEquals(165000000.0, $data[0]['net']);
        $this->assertEquals(14000000.0, $data[0]['overtime']);
        $this->assertEquals(18000000.0, $data[0]['mission']);
    }

    public function test_employee_can_view_attendance_and_clock_records(): void
    {
        $user = User::factory()->create();
        $employee = HrmEmployee::query()->create([
            'user_id' => $user->id,
            'employee_code' => 'ESS-EMP-02',
            'first_name' => 'فرهاد',
            'last_name' => 'افشار',
            'base_salary' => 100000000,
            'is_active' => true,
        ]);

        HrmAttendanceRecord::query()->create([
            'employee_id' => $employee->id,
            'date' => '2026-03-25',
            'check_in' => '08:15:00',
            'check_out' => '17:00:00',
            'status' => 'present',
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/hrm/me/attendance');

        $response->assertStatus(200);
        $items = $response->json('data.items');
        $this->assertCount(1, $items);
        $this->assertEquals('2026-03-25', $items[0]['work_date']);
        $this->assertEquals('08:15:00', $items[0]['check_in']);
        $this->assertEquals('17:00:00', $items[0]['check_out']);
    }

    public function test_employee_can_view_decrees_and_contract_details(): void
    {
        $user = User::factory()->create();
        $employee = HrmEmployee::query()->create([
            'user_id' => $user->id,
            'employee_code' => 'ESS-EMP-03',
            'first_name' => 'لیلا',
            'last_name' => 'حاتمی',
            'position' => 'مدیر کنترل کیفیت',
            'base_salary' => 120000000,
            'is_active' => true,
        ]);

        HrmEmploymentDecree::query()->create([
            'employee_id' => $employee->id,
            'decree_no' => 'DEC-ESS-99',
            'decree_type' => 'حکم ارتقاء رتبه',
            'status' => 'approved',
            'job_title' => 'مدیر کنترل کیفیت',
            'department' => 'تولید',
            'base_salary' => 140000000,
            'daily_wage' => 4666666.67,
            'contract_type' => 'permanent',
            'engagement_type' => 'full_time',
            'effective_from' => '2026-04-01',
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/hrm/me/decrees');

        $response->assertStatus(200);
        $decrees = $response->json('data.decrees');
        $this->assertCount(1, $decrees);
        $this->assertEquals('DEC-ESS-99', $decrees[0]['decree_no']);
        $this->assertEquals(140000000.0, $decrees[0]['base_salary']);
        $this->assertEquals('permanent', $decrees[0]['contract_type']);
    }

    public function test_employee_can_submit_mission_and_loan_requests_through_workflow(): void
    {
        $user = User::factory()->create();
        $employee = HrmEmployee::query()->create([
            'user_id' => $user->id,
            'employee_code' => 'ESS-EMP-04',
            'first_name' => 'پویا',
            'last_name' => 'دانشگر',
            'base_salary' => 130000000,
            'is_active' => true,
        ]);

        // 1. Submit Mission Request
        $missionResponse = $this->actingAs($user)->postJson('/api/v1/hrm/me/requests', [
            'type' => 'mission',
            'notes' => 'مأموریت نظارت فنی به پروژه شیراز',
            'payload' => [
                'days' => 3,
                'destination' => 'شیراز',
                'amount' => 15000000,
                'starts_at' => '2026-04-10 08:00:00',
                'ends_at' => '2026-04-13 18:00:00',
            ],
        ]);

        $missionResponse->assertStatus(201);
        $this->assertDatabaseHas('hrm_requests', [
            'employee_id' => $employee->id,
            'type' => 'mission',
        ]);
        $this->assertDatabaseHas('hrm_mission_orders', [
            'employee_id' => $employee->id,
            'destination' => 'شیراز',
        ]);

        // 2. Submit Loan Request
        $loanResponse = $this->actingAs($user)->postJson('/api/v1/hrm/me/requests', [
            'type' => 'loan',
            'notes' => 'درخواست وام ضروری پرسنلی',
            'payload' => [
                'amount' => 50000000,
                'installments' => 5,
            ],
        ]);

        $loanResponse->assertStatus(201);
        $this->assertDatabaseHas('hrm_loans', [
            'employee_id' => $employee->id,
            'principal' => 50000000,
            'installment_amount' => 10000000,
            'status' => 'active',
        ]);
    }
}

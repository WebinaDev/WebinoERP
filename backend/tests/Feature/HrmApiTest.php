<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Entities\SystemModule;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmLeaveRequest;
use Modules\Hrm\Entities\HrmPayrollRun;
use Modules\Hrm\Entities\HrmPayrollSetting;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class HrmApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        SystemModule::create(['name' => 'HRM', 'slug' => 'hrm', 'is_active' => true]);
    }

    public function test_employee_crud(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/v1/hrm/employees', [
            'employee_code' => 'EMP-001',
            'first_name' => 'Ali',
            'last_name' => 'Rezaei',
            'status' => 'active',
            'base_salary' => 166255500,
            'engagement_type' => 'full_time',
            'pay_basis' => 'monthly',
        ]);
        $create->assertCreated();
        $id = $create->json('data.id');

        $this->getJson('/api/v1/hrm/employees/'.$id)->assertOk();
        $this->patchJson('/api/v1/hrm/employees/'.$id, ['position' => 'Developer'])->assertOk();
        $del = $this->deleteJson('/api/v1/hrm/employees/'.$id);
        $this->assertTrue(in_array($del->status(), [204, 200, 404], true), 'delete status '.$del->status());
    }

    public function test_nested_attendance_check_in_out(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);
        $employee = HrmEmployee::create([
            'employee_code' => 'EMP-002',
            'first_name' => 'Sara',
            'last_name' => 'Ahmadi',
            'status' => 'active',
        ]);

        $this->postJson('/api/v1/hrm/attendance/check-in', ['employee_id' => $employee->id])->assertOk();
        $this->postJson('/api/v1/hrm/attendance/check-out', ['employee_id' => $employee->id])->assertOk();
    }

    public function test_nested_leave_approve_reject(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);
        $employee = HrmEmployee::create([
            'employee_code' => 'EMP-003',
            'first_name' => 'Reza',
            'last_name' => 'Karimi',
            'status' => 'active',
        ]);
        $leave = HrmLeaveRequest::create([
            'employee_id' => $employee->id,
            'type' => 'annual',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'status' => 'pending',
        ]);

        $this->postJson('/api/v1/hrm/leave/requests/'.$leave->id.'/approve')->assertOk();
        $leave->update(['status' => 'pending']);
        $this->postJson('/api/v1/hrm/leave/requests/'.$leave->id.'/reject')->assertOk();
    }

    public function test_nested_payroll_calculate_approve(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);
        HrmEmployee::create([
            'employee_code' => 'EMP-004',
            'first_name' => 'Neda',
            'last_name' => 'Hosseini',
            'status' => 'active',
            'engagement_type' => 'full_time',
            'pay_basis' => 'monthly',
            'base_salary' => 166255500,
        ]);
        $run = HrmPayrollRun::create([
            'title' => 'Pay 1404/01',
            'year' => 2025,
            'month' => 3,
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        $this->postJson('/api/v1/hrm/payroll/runs/'.$run->id.'/calculate')->assertOk();
        $this->postJson('/api/v1/hrm/payroll/runs/'.$run->id.'/approve')->assertOk();
        $this->getJson('/api/v1/hrm/payroll/runs/'.$run->id.'/payslips')->assertOk();
    }

    public function test_index_smoke_endpoints(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/hrm/attendance')->assertOk();
        $this->getJson('/api/v1/hrm/leave')->assertOk();
        $this->getJson('/api/v1/hrm/recruitment')->assertOk();
        $this->getJson('/api/v1/hrm/performance')->assertOk();
        $this->getJson('/api/v1/hrm/training')->assertOk();
    }

    public function test_individual_payslip_respects_minimum_and_freelance(): void
    {
        $user = $this->actingAsRole('hr_manager');
        Sanctum::actingAs($user);

        HrmPayrollSetting::query()->updateOrCreate(
            ['key' => 'minimum_monthly_wage'],
            ['value' => 100000000]
        );

        $fullTime = HrmEmployee::create([
            'employee_code' => 'EMP-FT',
            'first_name' => 'Mina',
            'last_name' => 'Karimi',
            'status' => 'active',
            'engagement_type' => 'full_time',
            'pay_basis' => 'monthly',
            'base_salary' => 1000,
            'insurance_applicable' => true,
            'tax_applicable' => true,
        ]);

        $this->postJson('/api/v1/hrm/payroll/payslips', [
            'employee_id' => $fullTime->id,
            'year' => 1405,
            'month' => 1,
        ])->assertStatus(422);

        $fullTime->update(['base_salary' => 120000000]);
        $ok = $this->postJson('/api/v1/hrm/payroll/payslips', [
            'employee_id' => $fullTime->id,
            'year' => 1405,
            'month' => 1,
        ]);
        $ok->assertCreated();
        $ok->assertJsonPath('data.item.breakdown.lines.bon', 22000000);
        $this->assertNotNull($ok->json('data.html'));
        $this->assertStringContainsString('فیش حقوقی', (string) $ok->json('data.html'));

        $freelance = HrmEmployee::create([
            'employee_code' => 'EMP-FL',
            'first_name' => 'Omid',
            'last_name' => 'Nouri',
            'status' => 'active',
            'engagement_type' => 'freelance',
            'pay_basis' => 'project_fee',
            'project_fee' => 500000,
            'base_salary' => 0,
            'insurance_applicable' => false,
            'tax_applicable' => true,
        ]);
        $slip = $this->postJson('/api/v1/hrm/payroll/payslips', [
            'employee_id' => $freelance->id,
            'year' => 1405,
            'month' => 2,
        ]);
        $slip->assertCreated();
        $slip->assertJsonPath('data.item.breakdown.insurance_applicable', false);
        $this->assertEquals(500000.0, (float) $slip->json('data.item.gross'));
    }


    public function test_1405_settings_seeded_and_eydi_prorate(): void
    {
        $user = $this->actingAsRole('hr_manager');
        Sanctum::actingAs($user);

        // Simulate migration seed keys
        foreach ([
            'law_year' => 1405,
            'law_year_label' => '۱۴۰۵',
            'minimum_monthly_wage' => 166255500,
            'minimum_daily_wage' => 5541850,
            'minimum_hourly_wage' => 756050,
            'eydi_months_factor' => 2,
            'eydi_cap_min_wage_months' => 3,
            'eydi_prorate' => true,
            'eydi_month' => 12,
        ] as $k => $v) {
            HrmPayrollSetting::query()->updateOrCreate(['key' => $k], ['value' => $v]);
        }

        $settings = $this->getJson('/api/v1/hrm/payroll/settings')->assertOk()->json('data');
        $this->assertEquals(1405, (int) $settings['law_year']);
        $this->assertEquals(166255500, (float) $settings['minimum_monthly_wage']);

        $emp = HrmEmployee::create([
            'employee_code' => 'EMP-1405',
            'first_name' => 'Ava',
            'last_name' => 'Rostami',
            'status' => 'active',
            'engagement_type' => 'full_time',
            'pay_basis' => 'monthly',
            'base_salary' => 166255500,
            'hire_date' => now()->subMonths(6)->toDateString(),
            'insurance_applicable' => true,
            'tax_applicable' => true,
        ]);

        $calc = app(\Modules\Hrm\Services\IranianPayrollCalculator::class);
        $eydi = $calc->eydiAmount(166255500, $calc->settings(), $emp);
        // 2×base capped at 3×min = 2×base; prorate ~6/12 => ~base
        $this->assertGreaterThan(0, $eydi);
        $this->assertLessThanOrEqual(166255500 * 2 + 1, $eydi);

        $preview = $this->postJson('/api/v1/hrm/payroll/severance-preview', [
            'employee_id' => $emp->id,
        ])->assertOk()->json('data');
        $this->assertEquals(1405, (int) $preview['law_year']);
        $this->assertArrayHasKey('severance', $preview);
    }

    public function test_multi_step_request_approval_chain(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        HrmPayrollSetting::query()->updateOrCreate(
            ['key' => 'approval_chain'],
            ['value' => ['manager', 'hr', 'finance']]
        );

        $emp = HrmEmployee::create([
            'employee_code' => 'EMP-REQ',
            'first_name' => 'Nima',
            'last_name' => 'Saedi',
            'status' => 'active',
            'user_id' => $user->id,
        ]);

        $wf = app(\Modules\Hrm\Services\HrmApprovalWorkflow::class);
        $boot = $wf->bootstrapFields();
        $req = \Modules\Hrm\Entities\HrmRequest::query()->create(array_merge([
            'employee_id' => $emp->id,
            'user_id' => $user->id,
            'type' => 'overtime',
            'payload' => ['hours' => 2, 'year' => 1405, 'month' => 1],
        ], $boot));

        $this->assertEquals('pending_manager', $req->status);

        $this->postJson('/api/v1/hrm/requests/'.$req->id.'/approve')->assertOk();
        $req->refresh();
        $this->assertEquals('pending_hr', $req->status);

        $this->postJson('/api/v1/hrm/requests/'.$req->id.'/approve')->assertOk();
        $req->refresh();
        $this->assertEquals('pending_finance', $req->status);

        $this->postJson('/api/v1/hrm/requests/'.$req->id.'/approve')->assertOk();
        $req->refresh();
        $this->assertEquals('approved', $req->status);

        $inbox = $this->getJson('/api/v1/hrm/requests/inbox')->assertOk()->json('data');
        $this->assertArrayHasKey('requests', $inbox);
        $this->assertArrayHasKey('leaves', $inbox);
    }

}

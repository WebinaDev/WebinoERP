<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Entities\SystemModule;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmLeaveRequest;
use Modules\Hrm\Entities\HrmPayrollRun;
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
        ]);
        $create->assertCreated();
        $id = $create->json('data.id');

        $this->getJson('/api/v1/hrm/employees/'.$id)->assertOk();
        $this->patchJson('/api/v1/hrm/employees/'.$id, ['position' => 'Developer'])->assertOk();
        $this->deleteJson('/api/v1/hrm/employees/'.$id)->assertNoContent();
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
            'base_salary' => 50000000,
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
}

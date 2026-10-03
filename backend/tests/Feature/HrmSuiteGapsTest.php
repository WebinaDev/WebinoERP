<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Entities\SystemModule;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmEmployeeProfile;
use Modules\Hrm\Entities\HrmLeaveRequest;
use Modules\Hrm\Entities\HrmOrgPosition;
use Modules\Hrm\Entities\HrmPayrollItem;
use Modules\Hrm\Entities\HrmPayrollRun;
use Modules\Hrm\Entities\HrmTrainingCourse;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class HrmSuiteGapsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        SystemModule::create(['name' => 'HRM', 'slug' => 'hrm', 'is_active' => true]);
        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_shift_assignment_conflicts_with_approved_leave(): void
    {
        $user = $this->actingAsRole('hr_manager');
        Sanctum::actingAs($user);
        $employee = HrmEmployee::create([
            'employee_code' => 'SH-1',
            'first_name' => 'علی',
            'last_name' => 'رضایی',
            'status' => 'active',
        ]);
        $template = $this->postJson('/api/v1/hrm/shifts/templates', [
            'name' => 'صبح',
            'start_time' => '08:00',
            'end_time' => '16:00',
        ])->assertCreated()->json('data.id');
        $date = now()->toDateString();
        HrmLeaveRequest::create([
            'employee_id' => $employee->id,
            'type' => 'annual',
            'start_date' => $date,
            'end_date' => $date,
            'status' => 'approved',
        ]);

        $this->postJson('/api/v1/hrm/shifts/assignments', [
            'employee_id' => $employee->id,
            'shift_template_id' => $template,
            'work_date' => $date,
        ])->assertStatus(422)->assertJsonPath('errors.conflicts.0.type', 'leave');

        $this->postJson('/api/v1/hrm/shifts/assignments', [
            'employee_id' => $employee->id,
            'shift_template_id' => $template,
            'work_date' => $date,
            'force' => true,
        ])->assertCreated();
    }

    public function test_hire_starts_onboarding_and_document_completes_task(): void
    {
        $user = $this->actingAsRole('hr_manager');
        Sanctum::actingAs($user);
        $template = $this->postJson('/api/v1/hrm/onboarding/templates', [
            'name' => 'ورود به کار',
            'items' => [
                ['title' => 'کارت ملی', 'document_category' => 'national_card', 'sort_order' => 1],
            ],
        ])->assertCreated()->json('data.id');
        $posting = $this->postJson('/api/v1/hrm/recruitment/postings', [
            'title' => 'کارشناس',
            'department' => 'مالی',
        ])->assertCreated()->json('data.id');
        $applicant = $this->postJson('/api/v1/hrm/recruitment/applicants', [
            'job_posting_id' => $posting,
            'first_name' => 'سارا',
            'last_name' => 'احمدی',
            'email' => 'sara@example.com',
        ])->assertCreated()->json('data.id');

        $hired = $this->postJson('/api/v1/hrm/recruitment/applicants/'.$applicant.'/hire', [
            'employee_code' => 'ON-1',
            'onboarding_template_id' => $template,
        ])->assertCreated();
        $employeeId = $hired->json('data.id');
        $this->assertNotNull($hired->json('data.onboarding.id'));
        $this->assertSame('pending', $hired->json('data.onboarding.tasks.0.status'));

        $this->post('/api/v1/hrm/staff/'.$employeeId.'/documents', [
            'title' => 'کارت',
            'category' => 'national_card',
            'file' => UploadedFile::fake()->create('id.pdf', 20, 'application/pdf'),
        ])->assertCreated();

        $this->getJson('/api/v1/hrm/onboarding')->assertOk()
            ->assertJsonFragment(['status' => 'completed']);
    }

    public function test_okr_360_lesson_certificate_succession_and_analytics(): void
    {
        $hr = $this->actingAsRole('hr_manager');
        Sanctum::actingAs($hr);
        $subject = HrmEmployee::create([
            'employee_code' => 'OK-1', 'first_name' => 'ندا', 'last_name' => 'حسینی', 'status' => 'active', 'user_id' => $hr->id,
        ]);
        $peerUser = $this->actingAsRole('team_member');
        $peer = HrmEmployee::create([
            'employee_code' => 'OK-2', 'first_name' => 'رضا', 'last_name' => 'کریمی', 'status' => 'active', 'user_id' => $peerUser->id,
        ]);

        Sanctum::actingAs($hr);
        $objective = $this->postJson('/api/v1/hrm/objectives', [
            'employee_id' => $subject->id,
            'title' => 'افزایش کیفیت',
            'key_results' => [['title' => 'تیکت', 'target_value' => 10, 'current_value' => 2]],
        ])->assertCreated();
        $kr = $objective->json('data.key_results.0.id');
        $this->patchJson('/api/v1/hrm/me/objectives/key-results/'.$kr, ['current_value' => 5])->assertOk()
            ->assertJsonPath('data.progress', 50);

        $review = $this->postJson('/api/v1/hrm/reviews-360', [
            'employee_id' => $subject->id,
            'raters' => [
                ['employee_id' => $subject->id, 'relationship' => 'self'],
                ['employee_id' => $peer->id, 'relationship' => 'peer'],
            ],
        ])->assertCreated();
        $peerRating = collect($review->json('data.ratings'))->firstWhere('rater_employee_id', $peer->id);

        Sanctum::actingAs($peerUser);
        $this->postJson('/api/v1/hrm/reviews-360', ['employee_id' => $subject->id])->assertForbidden();
        $this->postJson('/api/v1/hrm/me/reviews-360/ratings/'.$peerRating['id'].'/submit', [
            'score' => 80,
            'feedback' => 'خوب',
        ])->assertOk();

        Sanctum::actingAs($hr);
        $course = HrmTrainingCourse::create(['title' => 'ایمنی', 'status' => 'active']);
        $lesson = $this->postJson('/api/v1/hrm/training/lessons', [
            'course_id' => $course->id,
            'title' => 'درس ۱',
            'content_type' => 'link',
            'material_url' => 'https://example.com/lesson',
            'is_required' => true,
        ])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/hrm/training/enrollments', [
            'course_id' => $course->id,
            'employee_id' => $subject->id,
        ])->assertCreated();
        $done = $this->postJson('/api/v1/hrm/training/lessons/'.$lesson.'/complete', [
            'employee_id' => $subject->id,
            'progress_percent' => 100,
        ])->assertOk();
        $this->assertNotNull($done->json('data.certificate.serial_no'));
        $this->assertStringContainsString('گواهینامه', (string) $done->json('data.certificate.html'));

        $position = HrmOrgPosition::create(['title' => 'مدیر مالی', 'department' => 'مالی', 'is_active' => true]);
        $this->postJson('/api/v1/hrm/succession/plans', [
            'position_id' => $position->id,
            'successor_employee_id' => $peer->id,
            'readiness' => 'ready_now',
            'incumbent_employee_id' => $subject->id,
        ])->assertCreated();
        $this->getJson('/api/v1/hrm/succession/chart')->assertOk()
            ->assertJsonFragment(['title' => 'مدیر مالی', 'readiness' => 'ready_now']);

        $this->getJson('/api/v1/hrm/analytics/summary')->assertOk()
            ->assertJsonStructure(['data' => ['turnover' => ['rate', 'leavers', 'headcount'], 'labor_cost' => ['gross', 'net'], 'attendance_heatmap']]);
    }

    public function test_esign_bank_export_diskette_and_timesheet_scope(): void
    {
        $hr = $this->actingAsRole('hr_manager');
        Sanctum::actingAs($hr);
        $employee = HrmEmployee::create([
            'employee_code' => 'PAY-1', 'first_name' => 'مریم', 'last_name' => 'کاظمی', 'status' => 'active', 'user_id' => $hr->id,
        ]);
        HrmEmployeeProfile::create([
            'employee_id' => $employee->id,
            'national_id' => '0012345678',
            'sheba' => 'IR120170000000123456789001',
            'bank_name' => 'ملی',
            'custom_fields' => ['insurance_no' => '998877'],
        ]);
        $upload = $this->post('/api/v1/hrm/staff/'.$employee->id.'/documents', [
            'title' => 'حکم',
            'category' => 'decree',
            'file' => UploadedFile::fake()->create('decree.pdf', 10, 'application/pdf'),
        ])->assertCreated();
        $docId = $upload->json('data.id');
        $signed = $this->postJson('/api/v1/hrm/staff/'.$employee->id.'/documents/'.$docId.'/sign', [
            'signer_name' => 'مدیر منابع انسانی',
        ])->assertOk();
        $this->assertNotNull($signed->json('data.signed_at'));
        $this->assertSame('signed', $signed->json('data.document_status'));
        $this->assertNotEmpty($signed->json('data.audit_log'));

        $run = HrmPayrollRun::create([
            'title' => 'حقوق', 'year' => 2026, 'month' => 1, 'status' => 'approved', 'created_by' => $hr->id,
        ]);
        HrmPayrollItem::create([
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'gross' => 200000000,
            'deductions' => 20000000,
            'net' => 180000000,
            'breakdown' => [
                'insurable' => 180000000,
                'employee_insurance' => 12600000,
                'employer_insurance' => 36000000,
                'unemployment_insurance' => 5400000,
                'insurance_applicable' => true,
            ],
        ]);
        $csv = $this->get('/api/v1/hrm/payroll/runs/'.$run->id.'/bank-export?channel=paya');
        $csv->assertOk();
        $this->assertStringContainsString('IR120170000000123456789001', $csv->getContent());
        $this->assertStringContainsString('paya', $csv->getContent());
        $txt = $this->get('/api/v1/hrm/payroll/runs/'.$run->id.'/insurance-list?format=txt');
        $txt->assertOk();
        $this->assertStringContainsString('DSW_ID', $txt->getContent());
        $this->assertStringContainsString('998877', $txt->getContent());

        $member = $this->actingAsRole('team_member');
        $own = HrmEmployee::create([
            'employee_code' => 'TS-1', 'first_name' => 'کیان', 'last_name' => 'نوری', 'status' => 'active', 'user_id' => $member->id,
        ]);
        Sanctum::actingAs($member);
        $this->getJson('/api/v1/hrm/timesheets')->assertForbidden();
        $sheet = $this->postJson('/api/v1/hrm/me/timesheets', [
            'employee_id' => $employee->id,
            'work_date' => now()->toDateString(),
            'hours' => 3.5,
            'project_name' => 'وبینو',
            'task_name' => 'پشتیبانی',
        ])->assertCreated();
        $this->assertSame($own->id, $sheet->json('data.employee_id'));
        $this->postJson('/api/v1/hrm/me/timesheets/'.$sheet->json('data.id').'/submit')->assertOk();

        Sanctum::actingAs($hr);
        $this->postJson('/api/v1/hrm/timesheets/'.$sheet->json('data.id').'/decide', ['status' => 'approved'])->assertOk()
            ->assertJsonPath('data.status', 'approved');
    }
}

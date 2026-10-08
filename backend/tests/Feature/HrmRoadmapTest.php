<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Entities\SystemModule;
use Modules\Hrm\Entities\HrmAttendanceRecord;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmEmployeeProfile;
use Modules\Hrm\Entities\HrmLeaveRequest;
use Modules\Hrm\Entities\HrmOrgPosition;
use Modules\Hrm\Entities\HrmPayrollItem;
use Modules\Hrm\Entities\HrmPayrollRun;
use Modules\Hrm\Entities\HrmRequest;
use Modules\Hrm\Support\DbfWriter;
use Modules\Hrm\Support\SimpleSpreadsheet;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class HrmRoadmapTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        SystemModule::create(['name' => 'HRM', 'slug' => 'hrm', 'is_active' => true]);
    }

    private function employee(string $code, array $extra = []): HrmEmployee
    {
        return HrmEmployee::create(array_merge([
            'employee_code' => $code,
            'first_name' => 'کارمند',
            'last_name' => $code,
            'status' => 'active',
        ], $extra));
    }

    public function test_device_registry_ingest_mapping_and_csv_import(): void
    {
        Sanctum::actingAs($this->actingAsRole('hr_manager'));
        $ali = $this->employee('1001');
        $sara = $this->employee('1002');
        HrmEmployeeProfile::create(['employee_id' => $sara->id, 'national_id' => '0012345679']);
        $reza = $this->employee('1003');

        $created = $this->postJson('/api/v1/hrm/attendance/devices', [
            'name' => 'درب ورودی', 'device_code' => 'gate-1', 'vendor' => 'zkteco',
        ])->assertCreated();
        $key = $created->json('data.api_key');
        $deviceId = $created->json('data.id');
        $this->assertNotEmpty($key);
        $this->assertArrayNotHasKey('api_key_hash', $created->json('data'));

        $this->postJson('/api/v1/hrm/attendance/device-users', [
            'device_id' => $deviceId, 'device_user_id' => '77', 'employee_id' => $reza->id,
        ])->assertCreated();

        $this->postJson('/api/v1/hrm/attendance/ingest', ['punches' => []], ['X-Device-Id' => 'gate-1', 'X-Device-Key' => 'wrong'])
            ->assertStatus(401);

        $res = $this->postJson('/api/v1/hrm/attendance/ingest', ['punches' => [
            ['employee_code' => '1001', 'punched_at' => '2026-10-04 08:01:00', 'direction' => 'in'],
            ['employee_code' => '1001', 'punched_at' => '2026-10-04 17:05:00', 'status' => 1],
            ['national_id' => '۰۰۱۲۳۴۵۶۷۹', 'datetime' => '2026-10-04 08:10:00', 'status' => 0],
            ['pin' => '77', 'punched_at' => '2026-10-04 08:20:00'],
            ['pin' => '999', 'punched_at' => '2026-10-04 08:30:00'],
            ['pin' => '1001', 'punched_at' => 'not-a-date'],
        ]], ['X-Device-Id' => 'gate-1', 'X-Device-Key' => $key])->assertOk();
        $res->assertJsonPath('data.applied', 4)
            ->assertJsonPath('data.unmatched', 1)
            ->assertJsonPath('data.invalid', 1);

        $rec = HrmAttendanceRecord::query()->where('employee_id', $ali->id)->first();
        $this->assertSame('08:01:00', substr((string) $rec->check_in, -8));
        $this->assertSame('17:05:00', substr((string) $rec->check_out, -8));
        $this->assertSame('device', $rec->source);
        $this->assertTrue(HrmAttendanceRecord::query()->where('employee_id', $sara->id)->exists());
        $this->assertTrue(HrmAttendanceRecord::query()->where('employee_id', $reza->id)->exists());

        // Same punch again is a duplicate, a later check-out replaces the earlier one.
        $again = $this->postJson('/api/v1/hrm/attendance/ingest', ['punches' => [
            ['employee_code' => '1001', 'punched_at' => '2026-10-04 08:01:00', 'direction' => 'in'],
            ['employee_code' => '1001', 'punched_at' => '2026-10-04 18:00:00', 'direction' => 'out'],
        ]], ['X-Device-Id' => 'gate-1', 'X-Device-Key' => $key])->assertOk();
        $again->assertJsonPath('data.duplicate', 1)->assertJsonPath('data.applied', 1);
        $this->assertSame('18:00:00', substr((string) $rec->fresh()->check_out, -8));

        // Unmatched punch assigned by HR, mapping remembered.
        $punch = $this->getJson('/api/v1/hrm/attendance/punches?status=unmatched')->assertOk()->json('data.0');
        $this->assertSame('999', $punch['raw_code']);
        $this->postJson('/api/v1/hrm/attendance/punches/'.$punch['id'].'/assign', ['employee_id' => $sara->id])
            ->assertOk()->assertJsonPath('data.status', 'duplicate');
        $this->assertDatabaseHas('hrm_attendance_device_users', ['device_user_id' => '999', 'employee_id' => $sara->id]);

        // ZKTeco CSV export (tab separated, AC-No. header).
        $csv = "AC-No.\tName\tTime\tState\n1001\tAli\t2026-10-05 07:55:00\tC/In\n1001\tAli\t2026-10-05 16:30:00\tC/Out\n5555\tX\t2026-10-05 08:00:00\tC/In\n";
        $import = $this->post('/api/v1/hrm/attendance/import-csv', [
            'file' => UploadedFile::fake()->createWithContent('attlog.csv', $csv),
            'device_id' => $deviceId,
        ])->assertOk();
        $import->assertJsonPath('data.parsed', 3)->assertJsonPath('data.applied', 2)->assertJsonPath('data.unmatched', 1);
        $this->assertSame(1, count($import->json('data.issues')));
        $day = HrmAttendanceRecord::query()->where('employee_id', $ali->id)->whereDate('date', '2026-10-05')->first();
        $this->assertSame('16:30:00', substr((string) $day->check_out, -8));

        // Manual records are never overwritten.
        HrmAttendanceRecord::create(['employee_id' => $reza->id, 'date' => '2026-10-06', 'check_in' => '09:00:00', 'status' => 'present']);
        $this->postJson('/api/v1/hrm/attendance/ingest', ['punches' => [['pin' => '77', 'punched_at' => '2026-10-06 08:00:00']]],
            ['X-Device-Id' => 'gate-1', 'X-Device-Key' => $key])->assertJsonPath('data.conflict', 1);

        $rotated = $this->postJson('/api/v1/hrm/attendance/devices/'.$deviceId.'/rotate-key')->assertOk()->json('data.api_key');
        $this->postJson('/api/v1/hrm/attendance/ingest', ['punches' => []], ['X-Device-Id' => 'gate-1', 'X-Device-Key' => $key])->assertStatus(401);
        $this->postJson('/api/v1/hrm/attendance/ingest', ['punches' => []], ['X-Device-Id' => 'gate-1', 'X-Device-Key' => $rotated])->assertOk();
    }

    public function test_sso_period_export_builds_real_dbf_files(): void
    {
        $hr = $this->actingAsRole('hr_manager');
        Sanctum::actingAs($hr);
        $this->postJson('/api/v1/hrm/payroll/settings', ['settings' => [
            'workshop_code' => '1234567890', 'workshop_name' => 'کارگاه وبینو', 'employer_name' => 'شرکت وبینو', 'sso_list_no' => '01',
        ]])->assertOk();
        $emp = $this->employee('S-1', ['first_name' => 'مریم', 'last_name' => 'کاظمی']);
        HrmEmployeeProfile::create(['employee_id' => $emp->id, 'national_id' => '0012345679', 'insurance_number' => '9988776', 'father_name' => 'علی', 'gender' => 'female']);
        $run = HrmPayrollRun::create(['title' => 'مهر', 'year' => 1405, 'month' => 7, 'status' => 'approved', 'created_by' => $hr->id]);
        HrmPayrollItem::create([
            'payroll_run_id' => $run->id, 'employee_id' => $emp->id, 'gross' => 200000000, 'deductions' => 20000000, 'net' => 180000000,
            'breakdown' => ['base' => 150000000, 'insurable' => 180000000, 'employee_insurance' => 12600000, 'employer_insurance' => 36000000, 'unemployment_insurance' => 5400000, 'insurance_applicable' => true],
        ]);

        $json = $this->getJson('/api/v1/hrm/payroll/insurance-list?year=1405&month=7')->assertOk();
        $json->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.header.yy', 5)
            ->assertJsonPath('data.rows.0.days', 30)
            ->assertJsonPath('data.rows.0.benefits', 30000000)
            ->assertJsonPath('data.totals.employer_insurance', 36000000);

        $wor = $this->get('/api/v1/hrm/payroll/insurance-list?year=1405&month=7&format=dbf_wor')->assertOk()->getContent();
        $this->assertSame("\x03", $wor[0]);
        $parsed = DbfWriter::read($wor);
        $this->assertCount(27, $parsed['fields']);
        $rec = $parsed['records'][0];
        $this->assertSame('1234567890', $rec['DSW_ID']);
        $this->assertSame('9988776', $rec['DSW_ID1']);
        $this->assertSame('0012345679', $rec['PER_NATCOD']);
        $this->assertSame('180000000', $rec['DSW_MASH']);
        $this->assertSame('5', $rec['DSW_YY']);
        $this->assertStringContainsString('مريم', $rec['DSW_FNAME']);

        $kar = DbfWriter::read($this->get('/api/v1/hrm/payroll/insurance-list?year=1405&month=7&format=dbf_kar')->getContent());
        $this->assertSame('1', $kar['records'][0]['DSK_NUM']);
        $this->assertSame('36000000', $kar['records'][0]['DSK_TKOSO']);

        $zip = $this->get('/api/v1/hrm/payroll/insurance-list?year=1405&month=7&format=zip')->assertOk()->getContent();
        $this->assertStringStartsWith('PK', $zip);
        $txt = $this->get('/api/v1/hrm/payroll/runs/'.$run->id.'/insurance-list?format=dskkar')->assertOk()->getContent();
        $this->assertStringContainsString('DSK_ID', $txt);
    }

    public function test_offboarding_template_case_and_portal_progress(): void
    {
        $hr = $this->actingAsRole('hr_manager');
        $member = $this->actingAsRole('team_member');
        $emp = $this->employee('OFF-1', ['user_id' => $member->id]);
        Sanctum::actingAs($hr);
        $tpl = $this->postJson('/api/v1/hrm/offboarding/templates', [
            'name' => 'خروج استاندارد',
            'items' => [
                ['title' => 'تحویل لپ‌تاپ', 'kind' => 'asset_return', 'owner' => 'employee'],
                ['title' => 'قطع دسترسی‌ها', 'kind' => 'access_revoke', 'owner' => 'it'],
                ['title' => 'تسویه حساب', 'kind' => 'settlement', 'owner' => 'finance'],
                ['title' => 'مصاحبه خروج', 'kind' => 'exit_interview', 'owner' => 'hr', 'required' => false],
            ],
        ])->assertCreated()->json('data.id');
        $case = $this->postJson('/api/v1/hrm/offboarding/start', [
            'employee_id' => $emp->id, 'template_id' => $tpl, 'last_day' => '2026-10-30', 'reason' => 'resignation',
        ])->assertCreated();
        $case->assertJsonPath('data.progress.total', 4)->assertJsonPath('data.status', 'in_progress');
        $this->postJson('/api/v1/hrm/offboarding/start', ['employee_id' => $emp->id, 'template_id' => $tpl])->assertStatus(422);
        $tasks = collect($case->json('data.tasks'));

        Sanctum::actingAs($member);
        $mine = $this->getJson('/api/v1/hrm/me/offboarding')->assertOk();
        $this->assertCount(1, $mine->json('data'));
        $itTask = $tasks->firstWhere('owner', 'it');
        $this->postJson('/api/v1/hrm/me/offboarding/tasks/'.$itTask['id'].'/complete')->assertForbidden();
        $own = $tasks->firstWhere('owner', 'employee');
        $this->postJson('/api/v1/hrm/me/offboarding/tasks/'.$own['id'].'/complete', ['asset_label' => 'LT-22'])
            ->assertOk()->assertJsonPath('data.progress.done', 1);
        $this->getJson('/api/v1/hrm/offboarding')->assertForbidden();

        Sanctum::actingAs($hr);
        $this->postJson('/api/v1/hrm/offboarding/tasks/'.$itTask['id'].'/complete')->assertOk();
        $done = $this->postJson('/api/v1/hrm/offboarding/tasks/'.$tasks->firstWhere('kind', 'settlement')['id'].'/complete')->assertOk();
        $done->assertJsonPath('data.status', 'completed');
        $this->assertSame('terminated', $emp->fresh()->status);
        $this->getJson('/api/v1/hrm/offboarding')->assertOk()->assertJsonPath('data.0.progress.percent', 75);
    }

    public function test_configurable_approval_flow_drives_cartable(): void
    {
        $hr = $this->actingAsRole('hr_manager');
        $boss = $this->actingAsRole('team_member');
        $finance = $this->actingAsRole('finance_manager');
        $requester = $this->actingAsRole('team_member');
        $bossEmp = $this->employee('B-1', ['user_id' => $boss->id]);
        $emp = $this->employee('E-1', ['user_id' => $requester->id]);
        $top = HrmOrgPosition::create(['title' => 'مدیر', 'incumbent_employee_id' => $bossEmp->id]);
        HrmOrgPosition::create(['title' => 'کارشناس', 'parent_id' => $top->id, 'incumbent_employee_id' => $emp->id]);

        Sanctum::actingAs($hr);
        $this->postJson('/api/v1/hrm/approval-flows', [
            'request_type' => 'overtime',
            'steps' => [['type' => 'direct_manager'], ['type' => 'role', 'role' => 'finance_manager']],
        ])->assertCreated();
        $this->postJson('/api/v1/hrm/approval-flows', [
            'request_type' => 'overtime', 'steps' => [['type' => 'user']],
        ])->assertStatus(422);
        $index = $this->getJson('/api/v1/hrm/approval-flows')->assertOk();
        $this->assertContains('overtime', $index->json('data.request_types'));
        $this->assertNotEmpty($index->json('data.roles'));

        Sanctum::actingAs($requester);
        $req = $this->postJson('/api/v1/hrm/me/requests', ['type' => 'overtime', 'payload' => ['hours' => 3]])->assertCreated();
        $id = $req->json('data.id');
        $this->assertSame('pending_manager', HrmRequest::find($id)->status);

        Sanctum::actingAs($finance);
        $this->assertCount(0, $this->getJson('/api/v1/hrm/requests/inbox')->assertOk()->json('data.requests'));
        $this->postJson('/api/v1/hrm/requests/'.$id.'/approve')->assertForbidden();

        Sanctum::actingAs($boss);
        $this->assertCount(1, $this->getJson('/api/v1/hrm/requests/inbox')->assertOk()->json('data.requests'));
        $this->postJson('/api/v1/hrm/requests/'.$id.'/approve')->assertOk();
        $this->assertSame('pending_finance', HrmRequest::find($id)->status);

        Sanctum::actingAs($finance);
        $this->assertCount(1, $this->getJson('/api/v1/hrm/requests/inbox')->json('data.requests'));
        $this->postJson('/api/v1/hrm/requests/'.$id.'/approve')->assertOk();
        $this->assertSame('approved', HrmRequest::find($id)->status);

        // Leave flow with a specific user step, approved through the cartable leave route.
        Sanctum::actingAs($hr);
        $this->postJson('/api/v1/hrm/approval-flows', [
            'request_type' => 'leave', 'steps' => [['type' => 'user', 'user_ids' => [$finance->id]]],
        ])->assertCreated();
        $leave = $this->postJson('/api/v1/hrm/leave/requests', [
            'employee_id' => $emp->id, 'type' => 'annual', 'start_date' => '2026-11-01', 'end_date' => '2026-11-02',
        ])->assertCreated()->json('data.id');
        $this->assertSame('pending_approval', HrmLeaveRequest::find($leave)->status);
        $this->postJson('/api/v1/hrm/requests/leaves/'.$leave.'/approve')->assertForbidden();
        Sanctum::actingAs($finance);
        $this->assertCount(1, $this->getJson('/api/v1/hrm/requests/inbox')->json('data.leaves'));
        $this->postJson('/api/v1/hrm/requests/leaves/'.$leave.'/approve')->assertOk();
        $this->assertSame('approved', HrmLeaveRequest::find($leave)->status);
    }

    public function test_staff_spreadsheet_template_dry_run_and_import(): void
    {
        Sanctum::actingAs($this->actingAsRole('hr_manager'));
        $this->employee('2001');

        $xlsx = $this->get('/api/v1/hrm/staff/import-template?format=xlsx')->assertOk()->getContent();
        $path = tempnam(sys_get_temp_dir(), 'tpl').'.xlsx';
        file_put_contents($path, $xlsx);
        $rows = SimpleSpreadsheet::read($path, 'template.xlsx');
        $this->assertSame('کد پرسنلی', $rows[0][0]);
        $this->assertCount(2, $rows);

        $csv = "\xEF\xBB\xBF"."کد پرسنلی,نام,نام خانوادگی,کد ملی,موبایل,وضعیت,حقوق پایه,تاریخ استخدام,شماره بیمه\n"
            ."2001,علی,به‌روز,0012345679,09121234567,فعال,\"150,000,000\",1405/01/15,1234567\n"
            ."2002,سارا,جدید,۰۴۹۹۳۷۰۸۹۹,9351112233,آزمایشی,,,\n"
            ."2003,بد,کدملی,1234567890,0912,فعال,,,\n"
            ."2004,,بی‌نام,,,,,,\n"
            ."2002,تکراری,ردیف,,,,,,\n";
        $dry = $this->post('/api/v1/hrm/staff/import', [
            'file' => UploadedFile::fake()->createWithContent('staff.csv', $csv), 'dry_run' => 1,
        ])->assertOk();
        $dry->assertJsonPath('data.dry_run', true)->assertJsonPath('data.created', 1)->assertJsonPath('data.updated', 1)
            ->assertJsonPath('data.skipped', 3);
        $codes = collect($dry->json('data.errors'))->pluck('code')->all();
        $this->assertContains('invalid_national_id', $codes);
        $this->assertContains('invalid_mobile', $codes);
        $this->assertContains('required', $codes);
        $this->assertContains('duplicate_in_file', $codes);
        $this->assertDatabaseMissing('hrm_employees', ['employee_code' => '2002']);

        $this->post('/api/v1/hrm/staff/import', [
            'file' => UploadedFile::fake()->createWithContent('staff.csv', $csv),
        ])->assertOk()->assertJsonPath('data.created', 1)->assertJsonPath('data.updated', 1);
        $updated = HrmEmployee::query()->where('employee_code', '2001')->first();
        $this->assertSame('به‌روز', $updated->last_name);
        $this->assertSame(150000000.0, (float) $updated->base_salary);
        $this->assertSame('2026-04-04', $updated->hire_date->toDateString());
        $this->assertSame('1234567', $updated->profile->insurance_number);
        $new = HrmEmployee::query()->where('employee_code', '2002')->first();
        $this->assertSame('probation', $new->status);
        $this->assertSame('09351112233', $new->mobile);
        $this->assertSame('0499370899', $new->profile->national_id);

        $export = $this->get('/api/v1/hrm/staff/export?format=csv')->assertOk()->getContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $export);
        $this->assertStringContainsString('0499370899', $export);
    }

    public function test_calendar_feed_links_ics_and_regenerate(): void
    {
        $member = $this->actingAsRole('team_member');
        $emp = $this->employee('CAL-1', ['user_id' => $member->id]);
        HrmLeaveRequest::create(['employee_id' => $emp->id, 'type' => 'annual', 'start_date' => now()->addDays(3)->toDateString(), 'end_date' => now()->addDays(4)->toDateString(), 'status' => 'approved']);
        HrmLeaveRequest::create(['employee_id' => $emp->id, 'type' => 'sick', 'start_date' => now()->addDays(9)->toDateString(), 'end_date' => now()->addDays(9)->toDateString(), 'status' => 'pending']);
        Sanctum::actingAs($member);

        $links = $this->getJson('/api/v1/hrm/me/calendar/feed')->assertOk()->json('data');
        $this->assertStringContainsString('/api/v1/hrm/calendar/feeds/'.$links['token'].'.ics', $links['feed_url']);
        $this->assertStringStartsWith('webcal://', $links['webcal_url']);
        $this->assertStringContainsString('calendar.google.com', $links['google_url']);

        $ics = $this->get('/api/v1/hrm/calendar/feeds/'.$links['token'].'.ics')->assertOk();
        $this->assertStringContainsString('text/calendar', (string) $ics->headers->get('Content-Type'));
        $body = $ics->getContent();
        $this->assertStringContainsString('BEGIN:VCALENDAR', $body);
        $this->assertSame(1, substr_count($body, 'BEGIN:VEVENT'));
        $this->assertStringContainsString('مرخصی استحقاقی', $body);
        foreach (explode("\r\n", $body) as $line) {
            $this->assertLessThanOrEqual(75, strlen($line));
        }
        $this->get('/api/v1/hrm/me/calendar.ics')->assertOk();

        $new = $this->postJson('/api/v1/hrm/me/calendar/feed/regenerate')->assertOk()->json('data.token');
        $this->assertNotSame($links['token'], $new);
        $this->get('/api/v1/hrm/calendar/feeds/'.$links['token'].'.ics')->assertNotFound();
        $this->get('/api/v1/hrm/calendar/feeds/'.$new)->assertOk();
    }

    public function test_workforce_budget_vs_actual(): void
    {
        $hr = $this->actingAsRole('hr_manager');
        Sanctum::actingAs($hr);
        $a = $this->employee('W-1', ['department' => 'فروش']);
        $this->employee('W-2', ['department' => 'فروش']);
        $this->employee('W-3', ['department' => 'فنی']);
        $this->postJson('/api/v1/hrm/analytics/budgets', ['department' => 'فروش', 'year' => 1405, 'month' => null, 'headcount' => 3, 'cost_budget' => 1200000000])->assertCreated();
        $this->postJson('/api/v1/hrm/analytics/budgets', ['department' => 'فنی', 'year' => 1405, 'month' => 7, 'headcount' => 1, 'cost_budget' => 50000000])->assertCreated();
        $run = HrmPayrollRun::create(['title' => 'مهر', 'year' => 1405, 'month' => 7, 'status' => 'approved', 'created_by' => $hr->id]);
        HrmPayrollItem::create(['payroll_run_id' => $run->id, 'employee_id' => $a->id, 'gross' => 100000000, 'deductions' => 0, 'net' => 100000000, 'breakdown' => ['employer_insurance' => 20000000]]);

        $res = $this->getJson('/api/v1/hrm/analytics/budgets?year=1405&month=7')->assertOk();
        $rows = collect($res->json('data.rows'))->keyBy('department');
        $this->assertSame('annual_prorated', $rows['فروش']['basis']);
        $this->assertEquals(100000000, $rows['فروش']['cost_budget']);
        $this->assertEquals(120000000, $rows['فروش']['cost_actual']);
        $this->assertSame(2, $rows['فروش']['headcount_actual']);
        $this->assertSame(-1, $rows['فروش']['headcount_variance']);
        $this->assertSame('monthly', $rows['فنی']['basis']);
        $this->assertEquals(0, $rows['فنی']['cost_actual']);

        $member = $this->actingAsRole('team_member');
        Sanctum::actingAs($member);
        $this->postJson('/api/v1/hrm/analytics/budgets', ['department' => 'x', 'year' => 1405, 'headcount' => 1, 'cost_budget' => 1])->assertForbidden();
    }

    public function test_notification_channels_toggles_and_telegram_delivery(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true]), 'tapi.bale.ai/*' => Http::response(['ok' => true])]);
        $hr = $this->actingAsRole('hr_manager');
        $member = $this->actingAsRole('team_member');
        $emp = $this->employee('N-1', ['user_id' => $member->id, 'base_salary' => 150000000]);

        Sanctum::actingAs($member);
        $this->putJson('/api/v1/hrm/me/notification-channels', ['telegram_chat_id' => '123456', 'bale_chat_id' => '998877'])->assertOk()
            ->assertJsonPath('data.telegram_chat_id', '123456');

        Sanctum::actingAs($hr);
        $saved = $this->postJson('/api/v1/hrm/settings/notifications', [
            'enabled' => true,
            'channels' => ['in_app' => false, 'sms' => false, 'bale' => true, 'telegram' => true],
            'events' => ['payslip_issued' => true, 'leave_decision' => false],
            'telegram_bot_token' => 'TEST-TOKEN',
            'bale_bot_token' => 'BALE-TOKEN',
        ])->assertOk();
        $saved->assertJsonPath('data.telegram_token_set', true)->assertJsonPath('data.channels.telegram', true);
        $this->assertArrayNotHasKey('telegram_bot_token', $saved->json('data'));
        $this->assertArrayNotHasKey('telegram_bot_token', $this->getJson('/api/v1/hrm/payroll/settings')->json('data'));

        $test = $this->postJson('/api/v1/hrm/settings/notifications/test', ['employee_id' => $emp->id])->assertOk();
        $test->assertJsonPath('data.results.telegram', 'sent')->assertJsonPath('data.results.bale', 'sent')
            ->assertJsonPath('data.results.in_app', 'disabled');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'api.telegram.org/botTEST-TOKEN/sendMessage') && $r['chat_id'] === '123456');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'tapi.bale.ai/botBALE-TOKEN/sendMessage') && $r['chat_id'] === '998877');

        $before = count(Http::recorded());
        // Disabled event: leave decision sends nothing externally.
        $leave = HrmLeaveRequest::create(['employee_id' => $emp->id, 'type' => 'annual', 'start_date' => '2026-11-01', 'end_date' => '2026-11-01', 'status' => 'pending']);
        $this->postJson('/api/v1/hrm/leave/requests/'.$leave->id.'/reject', ['reason' => 'x'])->assertOk();
        $this->assertSame($before, count(Http::recorded()));
    }
}

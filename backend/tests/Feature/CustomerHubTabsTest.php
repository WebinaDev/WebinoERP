<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder as R;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Events\Customer\ContractSigned;
use Modules\Crm\Events\CustomerHubUpdated;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

/** Customer 360 phase 1 — documents / contracts / projects tabs (PLAN.md §5.4). */
class CustomerHubTabsTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    private CrmAccount $acc;

    private CrmAccount $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->seedLicensedModules();
        Storage::fake('local');
        $this->acc = CrmAccount::query()->create(['name' => 'Tabs Co', 'type' => 'customer', 'account_code' => 'C-31']);
        $this->other = CrmAccount::query()->create(['name' => 'Other Co', 'type' => 'customer', 'account_code' => 'C-32']);
    }

    private function base(?CrmAccount $a = null): string
    {
        return '/api/v1/crm/customers/'.($a ?? $this->acc)->id.'/hub';
    }

    private function admin(): User
    {
        $u = $this->actingAsRole(R::ROLE_SYSTEM_MANAGER);
        Sanctum::actingAs($u);

        return $u;
    }

    private function withPerms(array $perms): User
    {
        $u = User::factory()->create(['is_active' => true]);
        $u->givePermissionTo($perms);
        Sanctum::actingAs($u);

        return $u;
    }

    private function contract(CrmAccount $a, array $o = []): int
    {
        return DB::table('prj_contracts')->insertGetId($o + ['title' => 'C', 'status' => 'draft', 'amount' => 1000, 'customer_account_id' => $a->id, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function project(CrmAccount $a, array $o = []): int
    {
        return DB::table('prj_projects')->insertGetId($o + ['name' => 'P', 'status' => 'active', 'customer_account_id' => $a->id, 'is_template' => false, 'created_at' => now(), 'updated_at' => now()]);
    }

    // ---- documents -------------------------------------------------------

    public function test_upload_list_new_version_and_signed_link(): void
    {
        $this->admin();
        $res = $this->post($this->base().'/documents', ['title' => 'کارت ملی', 'category' => 'national_card', 'tags' => 'kyc,id',
            'file' => UploadedFile::fake()->create('id.pdf', 100, 'application/pdf')], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('data.version', 1)->assertJsonPath('data.has_file', true)->assertJsonPath('data.tags', ['kyc', 'id'])
            ->assertJsonPath('data.can.update', true);
        $docId = $res->json('data.id');

        $v2 = $this->post($this->base()."/documents/$docId/versions", ['file' => UploadedFile::fake()->create('id-v2.pdf', 50, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.version', 2)->assertJsonPath('data.review_status', 'pending')->assertJsonCount(1, 'data.versions');
        $newId = $v2->json('data.id');
        // old version cannot be replaced again
        $this->post($this->base()."/documents/$docId/versions", ['file' => UploadedFile::fake()->create('x.pdf', 5, 'application/pdf')], ['Accept' => 'application/json'])->assertStatus(422);

        $list = $this->getJson($this->base().'/documents?source=hub')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $newId);
        $this->assertSame(['current_page', 'per_page', 'total', 'last_page', 'can'], array_keys($list->json('meta')));

        $url = $this->postJson($this->base()."/documents/$newId/download-link")->assertOk()->json('data.url');
        $this->assertStringContainsString('signature=', $url);
        $this->get($url)->assertOk();
    }

    public function test_document_validation_filters_and_review(): void
    {
        $this->admin();
        $this->postJson($this->base().'/documents', ['title' => str_repeat('x', 256), 'category' => 'nope'])->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'category']);
        $this->post($this->base().'/documents', ['title' => 'x', 'category' => 'other', 'file' => UploadedFile::fake()->create('a.exe', 10)], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors(['file']);
        $this->post($this->base().'/documents', ['title' => 'x', 'category' => 'other', 'file' => UploadedFile::fake()->create('big.pdf', 20481, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors(['file']);

        $old = $this->postJson($this->base().'/documents', ['title' => 'Old permit', 'category' => 'permit', 'url' => 'https://x.ir/a', 'expires_at' => now()->subDay()->toDateString()])->assertCreated()->json('data.id');
        $this->postJson($this->base().'/documents', ['title' => 'Gazette', 'category' => 'gazette', 'url' => 'https://x.ir/b'])->assertCreated();
        $this->getJson($this->base().'/documents?expired=1')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $old);
        $this->getJson($this->base().'/documents?category=gazette')->assertJsonPath('meta.total', 1);
        $this->getJson($this->base().'/documents?q=permit')->assertJsonPath('meta.total', 1);
        $this->getJson($this->base().'/documents?per_page=1&page=2')->assertJsonPath('meta.last_page', 2)->assertJsonCount(1, 'data');
        $this->getJson($this->base().'/documents?per_page=101')->assertStatus(422);

        $this->patchJson($this->base()."/documents/$old", ['review_status' => 'approved', 'review_note' => 'ok'])->assertOk()
            ->assertJsonPath('data.review_status', 'approved')->assertJsonPath('data.reviewed_by.id', auth()->id());
        $this->deleteJson($this->base()."/documents/$old")->assertNoContent();
        $this->assertDatabaseMissing('crm_customer_documents', ['id' => $old]);
    }

    public function test_document_contract_and_project_must_belong_to_customer(): void
    {
        $this->admin();
        $foreignC = $this->contract($this->other);
        $foreignP = $this->project($this->other);
        $this->postJson($this->base().'/documents', ['title' => 'x', 'category' => 'other', 'url' => 'https://x.ir', 'contract_id' => $foreignC, 'project_id' => $foreignP])
            ->assertStatus(422)->assertJsonValidationErrors(['contract_id', 'project_id']);
        $own = $this->contract($this->acc);
        $this->postJson($this->base().'/documents', ['title' => 'x', 'category' => 'contract', 'url' => 'https://x.ir', 'contract_id' => $own])
            ->assertCreated()->assertJsonPath('data.contract.id', $own);
    }

    public function test_project_files_are_read_only_rows(): void
    {
        $this->admin();
        $pid = $this->project($this->acc);
        DB::table('prj_files')->insert(['project_id' => $pid, 'name' => 'design.fig', 'disk' => 'local', 'path' => 'p/x', 'size_bytes' => 10, 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->getJson($this->base().'/documents?source=projects')->assertOk()->assertJsonPath('data.0.source', 'projects')
            ->assertJsonPath('data.0.can', ['update' => false, 'delete' => false, 'review' => false]);
    }

    // ---- isolation & permissions ----------------------------------------

    public function test_records_of_another_customer_return_404(): void
    {
        $this->admin();
        $doc = DB::table('crm_customer_documents')->insertGetId(['account_id' => $this->other->id, 'category' => 'other', 'title' => 'x', 'created_at' => now(), 'updated_at' => now()]);
        $c = $this->contract($this->other);
        $p = $this->project($this->other);
        $this->getJson($this->base()."/documents/$doc")->assertNotFound();
        $this->patchJson($this->base()."/documents/$doc", ['title' => 'y'])->assertNotFound();
        $this->deleteJson($this->base()."/documents/$doc")->assertNotFound();
        $this->getJson($this->base()."/contracts/$c")->assertNotFound();
        $this->patchJson($this->base()."/contracts/$c", ['title' => 'y'])->assertNotFound();
        $this->postJson($this->base()."/contracts/$c/cancel", ['reason' => 'x'])->assertNotFound();
        $this->getJson($this->base()."/projects/$p")->assertNotFound();
        $this->postJson($this->base()."/projects/$p/tasks", ['title' => 't'])->assertNotFound();
    }

    public function test_sections_require_permission(): void
    {
        $this->withPerms(['crm.hub.documents.view']);
        $this->getJson($this->base().'/documents')->assertOk()->assertJsonPath('meta.can.create', false);
        $this->postJson($this->base().'/documents', ['title' => 'x', 'category' => 'other', 'url' => 'https://x.ir'])->assertForbidden();
        $this->getJson($this->base().'/contracts')->assertForbidden();
        $this->postJson($this->base().'/contracts', ['title' => 'x', 'amount' => 1])->assertForbidden();
        $this->getJson($this->base().'/projects')->assertForbidden();
        $this->postJson($this->base().'/projects', ['name' => 'x'])->assertForbidden();
    }

    // ---- contracts -------------------------------------------------------

    public function test_contract_create_validation(): void
    {
        $this->admin();
        $this->postJson($this->base().'/contracts', [])->assertStatus(422)->assertJsonValidationErrors(['title', 'amount']);
        $this->postJson($this->base().'/contracts', ['title' => 'x', 'amount' => -1])->assertStatus(422)->assertJsonValidationErrors(['amount']);
        $this->postJson($this->base().'/contracts', ['title' => 'x', 'amount' => 100, 'start_date' => '2026-05-01', 'end_date' => '2026-04-01'])
            ->assertStatus(422)->assertJsonValidationErrors(['end_date']);
        $this->postJson($this->base().'/contracts', ['title' => 'x', 'amount' => 100, 'installments' => [['due_date' => '2026-11-01', 'amount' => 60], ['due_date' => '2026-12-01', 'amount' => 41]]])
            ->assertStatus(422)->assertJsonValidationErrors(['installments']);
        $this->postJson($this->base().'/contracts', ['title' => 'x', 'amount' => 100, 'project_ids' => [$this->project($this->other)]])
            ->assertStatus(422)->assertJsonValidationErrors(['project_ids.0']);
    }

    public function test_contract_crud_kpis_installments_and_invoice(): void
    {
        $this->admin();
        $pid = $this->project($this->acc);
        $res = $this->postJson($this->base().'/contracts', [
            'customer_account_id' => $this->other->id, // must be ignored
            'title' => 'پشتیبانی سالانه', 'amount' => 1200, 'contract_no' => 'CT-1', 'start_date' => '2026-10-01', 'end_date' => '2027-09-30',
            'renewal_type' => 'manual', 'tax_pct' => 10, 'project_ids' => [$pid],
            'installments' => [['due_date' => '2026-01-01', 'amount' => 600, 'title' => 'پیش'], ['due_date' => '2027-06-01', 'amount' => 600]],
        ])->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.contract_no', 'CT-1')
            ->assertJsonPath('data.projects.0.id', $pid)->assertJsonPath('data.installments.0.status', 'overdue')
            ->assertJsonPath('data.remaining', 1200)->assertJsonPath('data.can.delete', true);
        $cid = $res->json('data.id');
        $this->assertDatabaseHas('prj_contracts', ['id' => $cid, 'customer_account_id' => $this->acc->id]);
        $this->assertDatabaseHas('prj_projects', ['id' => $pid, 'contract_id' => $cid]);

        $instId = $res->json('data.installments.0.id');
        $inv = $this->postJson($this->base()."/contracts/$cid/installments/$instId/invoice")->assertCreated()->json('data.invoice_id');
        $this->assertDatabaseHas('prj_pro_invoices', ['id' => $inv, 'contract_id' => $cid]);
        $this->postJson($this->base()."/contracts/$cid/installments/$instId/invoice")->assertOk()->assertJsonPath('data.invoice_id', $inv);
        $this->getJson($this->base()."/contracts/$cid")->assertJsonPath('data.invoiced_total', 600)->assertJsonPath('data.installments.0.invoice_id', $inv)
            ->assertJsonPath('data.invoices.0.id', $inv);

        $this->patchJson($this->base()."/contracts/$cid", ['status' => 'cancelled'])->assertStatus(422);
        $this->patchJson($this->base()."/contracts/$cid", ['end_date' => '2026-01-01'])->assertStatus(422)->assertJsonValidationErrors(['end_date']);
        $this->patchJson($this->base()."/contracts/$cid", ['installments' => [['due_date' => '2027-01-01', 'amount' => 1300]]])->assertStatus(422);
        $this->patchJson($this->base()."/contracts/$cid", ['status' => 'active', 'amount' => 1500])->assertOk()->assertJsonPath('data.status', 'active');

        $this->getJson($this->base().'/contracts?status=active')->assertJsonPath('meta.total', 1)->assertJsonPath('meta.kpis.active', 1);
        $this->getJson($this->base().'/contracts?q=CT-1')->assertJsonPath('meta.total', 1);
        $this->getJson($this->base().'/contracts?status=draft')->assertJsonPath('meta.total', 0);
        $this->deleteJson($this->base()."/contracts/$cid")->assertStatus(422);
        $this->postJson($this->base()."/contracts/$cid/cancel", [])->assertStatus(422)->assertJsonValidationErrors(['reason']);
        $this->postJson($this->base()."/contracts/$cid/cancel", ['reason' => 'مشتری انصراف داد'])->assertOk()->assertJsonPath('data.status', 'cancelled');

        $draft = $this->postJson($this->base().'/contracts', ['title' => 'd', 'amount' => 0])->json('data.id');
        $this->deleteJson($this->base()."/contracts/$draft")->assertNoContent();
    }

    public function test_sign_creates_document_status_and_event(): void
    {
        Event::fake([ContractSigned::class, CustomerHubUpdated::class]);
        $this->admin();
        $cid = $this->contract($this->acc);
        $res = $this->post($this->base()."/contracts/$cid/sign", ['signed_at' => '2026-10-20', 'file' => UploadedFile::fake()->create('signed.pdf', 30, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('data.status', 'signed');
        $docId = $res->json('data.signed_document.id');
        $this->assertNotNull($docId);
        $this->assertDatabaseHas('crm_customer_documents', ['id' => $docId, 'account_id' => $this->acc->id, 'category' => 'contract', 'contract_id' => $cid]);
        Event::assertDispatched(ContractSigned::class, fn ($e) => $e->contractId === $cid && $e->documentId === $docId);
        Event::assertDispatched(CustomerHubUpdated::class, fn ($e) => $e->action === 'contract.sign' && $e->accountId === $this->acc->id);
        $this->postJson($this->base()."/contracts/$cid/sign")->assertStatus(422);

        $renew = $this->postJson($this->base()."/contracts/$cid/renew")->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.renewed_from_id', $cid);
        $this->assertNotSame($cid, $renew->json('data.id'));
    }

    public function test_amounts_are_null_without_billing(): void
    {
        $cid = $this->contract($this->acc, ['status' => 'active']);
        DB::table('prj_contract_installments')->insert(['contract_id' => $cid, 'amount' => 100, 'due_date' => '2027-01-01', 'created_at' => now(), 'updated_at' => now()]);
        $pid = $this->project($this->acc, ['budget_amount' => 5000]);
        $this->withPerms(['crm.hub.contracts.view', 'crm.hub.projects.view']);
        $this->getJson($this->base()."/contracts/$cid")->assertOk()->assertJsonPath('data.paid_total', null)
            ->assertJsonPath('data.invoiced_total', null)->assertJsonPath('data.remaining', null)->assertJsonPath('data.installments.0.amount', null);
        $this->getJson($this->base().'/contracts')->assertJsonPath('meta.kpis.active_amount', null);
        $this->getJson($this->base()."/projects/$pid")->assertOk()->assertJsonPath('data.billable_amount', null)->assertJsonPath('data.invoiced_amount', null)->assertJsonPath('data.budget', null);

        $this->withPerms(['crm.hub.contracts.view', 'accounting.view']);
        $this->getJson($this->base()."/contracts/$cid")->assertJsonPath('data.remaining', 1000);
    }

    // ---- projects --------------------------------------------------------

    public function test_project_crud_tasks_progress_and_contract_link(): void
    {
        $this->admin();
        $cid = $this->contract($this->acc);
        $this->postJson($this->base().'/projects', ['name' => 'x', 'contract_id' => $this->contract($this->other)])->assertStatus(422)->assertJsonValidationErrors(['contract_id']);
        $this->postJson($this->base().'/projects', ['name' => 'x', 'start_date' => '2026-05-01', 'due_date' => '2026-04-01'])->assertStatus(422)->assertJsonValidationErrors(['due_date']);
        $res = $this->postJson($this->base().'/projects', ['name' => 'سایت', 'status' => 'planning', 'budget' => 900, 'contract_id' => $cid, 'start_date' => '2026-10-01', 'due_date' => '2026-12-01'])
            ->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.contract.id', $cid)->assertJsonPath('data.budget', 900)->assertJsonPath('data.progress_pct', 0);
        $pid = $res->json('data.id');
        $this->patchJson($this->base()."/projects/$pid", ['status' => 'completed'])->assertStatus(422); // draft → completed not allowed
        $this->patchJson($this->base()."/projects/$pid", ['status' => 'active'])->assertOk();
        $t1 = $this->postJson($this->base()."/projects/$pid/tasks", ['title' => 'طراحی', 'assignee_id' => auth()->id()])->assertCreated()->json('data.id');
        $this->postJson($this->base()."/projects/$pid/tasks", ['title' => 'توسعه', 'due_at' => now()->subDay()->toDateTimeString()])->assertCreated();
        $this->postJson($this->base()."/projects/$pid/tasks", [])->assertStatus(422)->assertJsonValidationErrors(['title']);
        DB::table('prj_tasks')->where('id', $t1)->update(['status' => 'done']);
        $show = $this->getJson($this->base()."/projects/$pid")->assertOk()
            ->assertJsonPath('data.tasks', ['total' => 2, 'done' => 1, 'overdue' => 1])->assertJsonPath('data.progress_pct', 50)
            ->assertJsonCount(1, 'data.open_tasks')->assertJsonPath('data.url', "/projects/$pid");
        $this->assertNotEmpty($show->json('data.members'));
        $this->getJson($this->base().'/projects?status=active')->assertJsonPath('meta.total', 1);
        $this->getJson($this->base().'/projects?status=cancelled')->assertJsonPath('meta.total', 0);
        $this->getJson($this->base()."/contracts/$cid")->assertJsonPath('data.projects.0.id', $pid);
    }

    public function test_writes_record_audit_and_broadcast(): void
    {
        Event::fake([CustomerHubUpdated::class]);
        $this->admin();
        $this->postJson($this->base().'/contracts', ['title' => 'a', 'amount' => 1])->assertCreated();
        $this->postJson($this->base().'/projects', ['name' => 'p'])->assertCreated();
        $this->postJson($this->base().'/documents', ['title' => 'd', 'category' => 'other', 'url' => 'https://x.ir'])->assertCreated();
        foreach (['contract.create', 'project.create', 'document.upload'] as $action) {
            $this->assertDatabaseHas('crm_customer_audit', ['account_id' => $this->acc->id, 'action' => $action]);
            Event::assertDispatched(CustomerHubUpdated::class, fn ($e) => $e->action === $action);
        }
    }
}

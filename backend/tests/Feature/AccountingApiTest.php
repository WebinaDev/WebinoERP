<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Accounting\Entities\AccChartAccount;
use Modules\Accounting\Entities\AccFiscalYear;
use Modules\Accounting\Entities\AccJournalEntry;
use Modules\Accounting\Entities\AccJournalLine;
use Modules\Accounting\Entities\AccountingInvoice;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Core\Entities\SystemModule;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class AccountingApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        SystemModule::create(['name' => 'Accounting', 'slug' => 'accounting', 'is_active' => true]);
    }

    public function test_finance_manager_can_list_journals_and_chart(): void
    {
        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_FINANCE_MANAGER);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/accounting/journals')->assertOk();
        $this->getJson('/api/v1/accounting/chart')->assertOk();
        $this->getJson('/api/v1/accounting/summary')->assertOk();
    }

    public function test_client_cannot_access_accounting(): void
    {
        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_CLIENT);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/accounting/journals')->assertForbidden();
    }

    public function test_finance_manager_can_crud_chart_accounts(): void
    {
        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_FINANCE_MANAGER);
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/v1/accounting/chart', [
            'code' => '9999',
            'name' => 'Test Account',
            'type' => 'asset',
            'is_postable' => true,
        ]);
        $create->assertCreated()
            ->assertJsonPath('data.code', '9999')
            ->assertJsonPath('data.name', 'Test Account');

        $id = $create->json('data.id');

        $this->patchJson("/api/v1/accounting/chart/{$id}", [
            'name' => 'Updated Account',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated Account');

        $this->deleteJson("/api/v1/accounting/chart/{$id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('acc_chart_accounts', ['id' => $id]);
    }

    public function test_finance_manager_can_fetch_ledger(): void
    {
        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_FINANCE_MANAGER);
        Sanctum::actingAs($user);

        $fy = AccFiscalYear::query()->create([
            'title' => '1404',
            'starts_on' => '2025-03-21',
            'ends_on' => '2026-03-20',
        ]);
        $account = AccChartAccount::query()->create([
            'code' => '1100',
            'name' => 'Cash',
            'type' => 'asset',
            'is_postable' => true,
        ]);
        $entry = AccJournalEntry::query()->create([
            'fiscal_year_id' => $fy->id,
            'document_no' => 'JE-001',
            'document_date' => '2025-06-01',
            'description' => 'Test entry',
            'status' => 'posted',
            'created_by' => $user->id,
        ]);
        AccJournalLine::query()->create([
            'journal_entry_id' => $entry->id,
            'account_id' => $account->id,
            'debit' => 1000,
            'credit' => 0,
            'description' => 'Debit line',
        ]);

        $this->getJson('/api/v1/accounting/ledger')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'lines',
                    'totals' => ['debit', 'credit'],
                ],
            ])
            ->assertJsonPath('data.totals.debit', 1000);
    }

    public function test_finance_manager_can_get_next_invoice_number(): void
    {
        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_FINANCE_MANAGER);
        Sanctum::actingAs($user);

        $fy = AccFiscalYear::query()->create([
            'title' => '1404',
            'starts_on' => '2025-03-21',
            'ends_on' => '2026-03-20',
        ]);

        AccountingInvoice::query()->create([
            'type' => 'sale',
            'number' => 'INV-00042',
            'fiscal_year_id' => $fy->id,
            'status' => 'draft',
            'subtotal' => 0,
            'tax' => 0,
            'total' => 0,
            'created_by' => $user->id,
        ]);

        $this->getJson('/api/v1/accounting/invoices/next-number?fiscal_year_id='.$fy->id)
            ->assertOk()
            ->assertJsonPath('data.next_number', 'INV-00043');
    }

    public function test_finance_manager_can_list_persons_with_search(): void
    {
        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_FINANCE_MANAGER);
        Sanctum::actingAs($user);

        \Modules\Accounting\Entities\AccPerson::query()->create([
            'name' => 'Vendor Alpha',
            'type' => 'vendor',
            'mobile' => '09121112233',
        ]);

        $this->getJson('/api/v1/accounting/persons?search=Alpha')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Vendor Alpha');
    }
}

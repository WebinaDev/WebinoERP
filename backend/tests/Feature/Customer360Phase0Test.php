<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Entities\CrmAccountAddress;
use Modules\Crm\Entities\CustomerDocument;
use Modules\Projects\Entities\Contract;
use Modules\Projects\Entities\Project;
use Tests\TestCase;

class Customer360Phase0Test extends TestCase
{
    use RefreshDatabase;

    public function test_new_columns_and_tables_exist(): void
    {
        foreach (['phone', 'email', 'tags', 'custom_fields', 'iban', 'account_manager_id', 'merged_into_id', 'last_activity_at', 'lifetime_value'] as $c) {
            $this->assertTrue(Schema::hasColumn('crm_accounts', $c), $c);
        }
        $this->assertTrue(Schema::hasTable('crm_account_addresses'));
        foreach (['docs_contracts', 'docs_files', 'marketplace_basalam_connections'] as $t) {
            $this->assertTrue(Schema::hasColumn($t, 'crm_account_id'), $t);
        }
        $this->assertTrue(Schema::hasColumn('prj_contracts', 'contract_no'));
        $this->assertTrue(Schema::hasColumn('prj_projects', 'contract_id'));
        $this->assertTrue(Schema::hasColumn('crm_customer_documents', 'version'));
    }

    public function test_relations_and_casts(): void
    {
        $a = CrmAccount::create(['type' => 'customer', 'name' => 'Ali', 'tags' => ['vip'], 'iban' => 'IR000000000000000000000001']);
        $child = CrmAccount::create(['type' => 'customer', 'name' => 'Child', 'parent_id' => $a->id]);

        CrmAccountAddress::create(['account_id' => $a->id, 'type' => 'billing', 'city' => 'Tehran', 'is_default' => true]);
        $contract = Contract::create(['title' => 'C1', 'amount' => 100, 'customer_account_id' => $a->id]);
        $project = Project::create(['name' => 'P1', 'customer_account_id' => $a->id]);
        CustomerDocument::create(['account_id' => $a->id, 'category' => 'contract', 'title' => 'D1', 'contract_id' => $contract->id, 'project_id' => $project->id]);

        $a->refresh();
        $this->assertSame(['vip'], $a->tags);
        $this->assertSame('IR000000000000000000000001', $a->iban);
        $this->assertNotSame('IR000000000000000000000001', DB::table('crm_accounts')->where('id', $a->id)->value('iban'));
        $this->assertArrayNotHasKey('iban', $a->toArray());

        $this->assertCount(1, $a->addresses);
        $this->assertCount(1, $a->contracts);
        $this->assertCount(1, $a->projects);
        $this->assertCount(1, $a->documents);
        $this->assertTrue($a->documents->first()->contract->is($contract));
        $this->assertTrue($child->parent->is($a));
        $this->assertCount(1, $a->children);

        foreach (['deals', 'leads', 'docContracts', 'docFiles', 'domains', 'services', 'invoices', 'licenses', 'appointments', 'marketplaceOrders', 'basalamConnections', 'platformProjects', 'platformResources'] as $rel) {
            $this->assertCount(0, $a->{$rel}, $rel);
        }
    }

    public function test_orphan_cleanup_nulls_dangling_references(): void
    {
        $a = CrmAccount::create(['type' => 'customer', 'name' => 'Real']);
        DB::table('docs_contracts')->insert([
            ['title' => 'ok', 'crm_account_id' => $a->id, 'created_at' => now(), 'updated_at' => now()],
            ['title' => 'orphan', 'crm_account_id' => 999999, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $m = require base_path('Modules/Crm/Database/Migrations/2026_10_24_000200_customer360_foreign_keys.php');
        $this->assertSame(1, $m::cleanOrphans('docs_contracts', 'crm_account_id', 'crm_accounts'));
        $this->assertSame($a->id, (int) DB::table('docs_contracts')->where('title', 'ok')->value('crm_account_id'));
        $this->assertNull(DB::table('docs_contracts')->where('title', 'orphan')->value('crm_account_id'));
        // idempotent
        $this->assertSame(0, $m::cleanOrphans('docs_contracts', 'crm_account_id', 'crm_accounts'));
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Customer 360 — phase 0 FKs. For each (table, column → parent): orphans are nulled first,
 * then a nullOnDelete FK is added if none exists. Skipped on SQLite (no ALTER ADD CONSTRAINT).
 */
return new class extends Migration
{
    /** @return array<int, array{0:string,1:string,2:string}> */
    public static function links(): array
    {
        return [
            ['core_licenses', 'crm_account_id', 'crm_accounts'],
            ['sales_invoices', 'customer_id', 'crm_accounts'],
            ['marketplace_orders', 'crm_account_id', 'crm_accounts'],
            ['platform_projects', 'crm_account_id', 'crm_accounts'],
            ['platform_resources', 'crm_account_id', 'crm_accounts'],
            ['docs_contracts', 'crm_account_id', 'crm_accounts'],
            ['docs_files', 'crm_account_id', 'crm_accounts'],
            ['marketplace_basalam_connections', 'crm_account_id', 'crm_accounts'],
            ['crm_accounts', 'merged_into_id', 'crm_accounts'],
            ['crm_customer_documents', 'parent_id', 'crm_customer_documents'],
            ['crm_customer_documents', 'contract_id', 'prj_contracts'],
            ['crm_customer_documents', 'project_id', 'prj_projects'],
            ['crm_customer_documents', 'reviewed_by', 'users'],
            ['prj_contracts', 'deal_id', 'crm_deals'],
            ['prj_contracts', 'signed_document_id', 'crm_customer_documents'],
            ['prj_contracts', 'owner_id', 'users'],
            ['prj_projects', 'contract_id', 'prj_contracts'],
        ];
    }

    public static function cleanOrphans(string $table, string $col, string $parent): int
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $col) || ! Schema::hasTable($parent)) {
            return 0;
        }

        return DB::table($table)->whereNotNull($col)
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from($parent.' as p')->whereColumn('p.id', $table.'.'.$col))
            ->update([$col => null]);
    }

    private function hasFk(string $table, string $col): bool
    {
        foreach (Schema::getForeignKeys($table) as $fk) {
            if (($fk['columns'] ?? []) === [$col]) {
                return true;
            }
        }

        return false;
    }

    public function up(): void
    {
        $sqlite = DB::getDriverName() === 'sqlite';
        foreach (self::links() as [$table, $col, $parent]) {
            $n = self::cleanOrphans($table, $col, $parent);
            if ($n > 0) {
                Log::info("customer360: nulled {$n} orphan {$table}.{$col}");
            }
            if ($sqlite || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $col) || ! Schema::hasTable($parent) || $this->hasFk($table, $col)) {
                continue;
            }
            try {
                Schema::table($table, fn (Blueprint $t) => $t->foreign($col, "c360_{$table}_{$col}_fk")->references('id')->on($parent)->nullOnDelete());
            } catch (Throwable $e) {
                // Never block a deploy on a legacy type mismatch; relation still works without the constraint.
                Log::warning("customer360: FK {$table}.{$col} skipped: ".$e->getMessage());
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        foreach (self::links() as [$table, $col]) {
            if (Schema::hasTable($table) && collect(Schema::getForeignKeys($table))->contains('name', "c360_{$table}_{$col}_fk")) {
                Schema::table($table, fn (Blueprint $t) => $t->dropForeign("c360_{$table}_{$col}_fk"));
            }
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer 360 phase 1 (tabs): only the columns Phase 0 (2026_10_24_000100_customer360_core) does not add.
 *  - prj_contract_installments.title / invoice_id  (installment label + idempotent "invoice this installment")
 *  - prj_contracts.renewed_from_id                 (renewal chain)
 */
return new class extends Migration
{
    public function up(): void
    {
        $add = [
            'prj_contract_installments' => [
                'title' => fn (Blueprint $t) => $t->string('title', 255)->nullable(),
                'invoice_id' => fn (Blueprint $t) => $t->unsignedBigInteger('invoice_id')->nullable()->index(),
            ],
            'prj_contracts' => [
                'renewed_from_id' => fn (Blueprint $t) => $t->unsignedBigInteger('renewed_from_id')->nullable()->index(),
            ],
        ];
        foreach ($add as $table => $cols) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $missing = array_filter($cols, fn ($c) => ! Schema::hasColumn($table, $c), ARRAY_FILTER_USE_KEY);
            if ($missing) {
                Schema::table($table, function (Blueprint $t) use ($missing) {
                    foreach ($missing as $fn) {
                        $fn($t);
                    }
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['prj_contract_installments' => ['title', 'invoice_id'], 'prj_contracts' => ['renewed_from_id']] as $table => $cols) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $present = array_values(array_filter($cols, fn ($c) => Schema::hasColumn($table, $c)));
            if ($present) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn($present));
            }
        }
    }
};

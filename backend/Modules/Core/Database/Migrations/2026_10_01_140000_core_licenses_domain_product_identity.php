<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Entitlement identity is domain (+ product), not a license code.
 * license_key remains as a deprecated internal column (filled for unique/compat).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('core_licenses', function (Blueprint $table) {
            if (! Schema::hasColumn('core_licenses', 'product')) {
                $table->string('product', 64)->default('webino')->after('domain');
            }
        });

        // Backfill product + ensure license_key is never empty for legacy unique index.
        if (Schema::hasTable('core_licenses')) {
            DB::table('core_licenses')->orderBy('id')->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    $domain = strtolower(trim((string) ($row->domain ?? '')));
                    $product = strtolower(trim((string) ($row->product ?? '')));
                    if ($product === '') {
                        $product = 'webino';
                    }
                    $key = trim((string) ($row->license_key ?? ''));
                    $updates = [];
                    if (($row->product ?? null) !== $product) {
                        $updates['product'] = $product;
                    }
                    if ($key === '' && $domain !== '') {
                        // Deterministic placeholder — not a user-facing code.
                        $updates['license_key'] = 'dom:'.$domain.($product !== 'webino' ? ':'.$product : '');
                    }
                    if ($updates !== []) {
                        DB::table('core_licenses')->where('id', $row->id)->update($updates);
                    }
                }
            });
        }

        Schema::table('core_licenses', function (Blueprint $table) {
            try {
                $table->dropUnique('core_licenses_domain_unique');
            } catch (Throwable) {
            }
            try {
                $table->unique(['domain', 'product'], 'core_licenses_domain_product_unique');
            } catch (Throwable) {
            }
        });
    }

    public function down(): void
    {
        Schema::table('core_licenses', function (Blueprint $table) {
            try {
                $table->dropUnique('core_licenses_domain_product_unique');
            } catch (Throwable) {
            }
            try {
                $table->unique('domain', 'core_licenses_domain_unique');
            } catch (Throwable) {
            }
            if (Schema::hasColumn('core_licenses', 'product')) {
                $table->dropColumn('product');
            }
        });
    }
};

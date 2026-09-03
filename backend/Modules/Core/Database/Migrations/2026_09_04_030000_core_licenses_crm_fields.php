<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('core_licenses', function (Blueprint $table) {
            if (! Schema::hasColumn('core_licenses', 'project_name')) {
                $table->string('project_name')->nullable()->after('license_key');
            }
            if (! Schema::hasColumn('core_licenses', 'logo_url')) {
                $table->string('logo_url', 1000)->nullable()->after('domain');
            }
            if (! Schema::hasColumn('core_licenses', 'start_date')) {
                $table->date('start_date')->nullable()->after('status');
            }
        });

        Schema::table('core_licenses', function (Blueprint $table) {
            // Unique domain when set (PostgreSQL treats NULL as distinct).
            try {
                $table->unique('domain', 'core_licenses_domain_unique');
            } catch (Throwable) {
                // Index may already exist.
            }
        });
    }

    public function down(): void
    {
        Schema::table('core_licenses', function (Blueprint $table) {
            try {
                $table->dropUnique('core_licenses_domain_unique');
            } catch (Throwable) {
            }
            foreach (['project_name', 'logo_url', 'start_date'] as $col) {
                if (Schema::hasColumn('core_licenses', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Register the modules added on 2026-11-16..18 (org, workflow, BI, field service, QM, assets,
 * authz, procurement, device gateway, AI content). Their routes are gated by `module:<slug>`
 * (CheckModuleLicense), but existing installs never re-run SystemModulesSeeder, so the rows
 * were missing and every endpoint answered MODULE_NOT_ACTIVE. Only missing rows are inserted;
 * existing rows (and their is_active choice) are left alone.
 */
return new class extends Migration
{
    private const MODULES = [
        'ai_content' => 'AI Content',
        'org' => 'Org',
        'workflow' => 'Workflow',
        'bi' => 'BI',
        'field_service' => 'Field Service',
        'qm' => 'QM',
        'assets' => 'Assets',
        'authz' => 'Authz',
        'procurement' => 'Procurement',
        'device_gateway' => 'Device Gateway',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('system_modules')) {
            return;
        }
        $now = now();
        foreach (self::MODULES as $slug => $name) {
            if (DB::table('system_modules')->where('slug', $slug)->orWhere('name', $name)->exists()) {
                continue;
            }
            DB::table('system_modules')->insert([
                'name' => $name,
                'slug' => $slug,
                'is_active' => true,
                'started_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Keep the rows: removing them would lock users out of data they created.
    }
};

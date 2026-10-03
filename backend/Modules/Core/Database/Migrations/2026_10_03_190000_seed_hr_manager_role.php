<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder;

/**
 * Idempotent: creates hr_manager (مدیر منابع انسانی) and hrm.ess.view
 * on databases that already ran the earlier roles migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        app()->make(RolesAndPermissionsSeeder::class)->run();
    }

    public function down(): void
    {
        // Roles are not removed on rollback.
    }
};

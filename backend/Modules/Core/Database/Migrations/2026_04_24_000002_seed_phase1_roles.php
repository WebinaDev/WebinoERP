<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder;

/**
 * Idempotent migration that runs the roles/permissions seeder. Ensures the four parity roles
 * (finance_manager, system_manager, team_member, sales_consultant + client) and the full
 * permission list exist in any environment that runs `php artisan migrate`.
 */
return new class extends Migration
{
    public function up(): void
    {
        app()->make(RolesAndPermissionsSeeder::class)->run();
    }

    public function down(): void
    {
        // We intentionally do NOT remove roles on rollback — data-destructive.
    }
};

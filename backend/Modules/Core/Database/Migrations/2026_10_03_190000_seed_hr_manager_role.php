<?php

use Illuminate\Database\Migrations\Migration;

// Initial roles are created by the installation command.
return new class extends Migration
{
    public function up(): void
    {
        \Modules\Core\Support\Initialization\LegacyUpgradeQueue::record('roles');
    }

    public function down(): void
    {
        // Roles are not removed on rollback.
    }
};

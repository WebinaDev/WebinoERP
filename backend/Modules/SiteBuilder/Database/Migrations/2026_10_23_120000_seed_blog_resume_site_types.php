<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

// Initial site types are created by the installation command.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('webino_business_types') || ! Schema::hasTable('webino_dashboard_features')) {
            return;
        }
        \Modules\Core\Support\Initialization\LegacyUpgradeQueue::record('site_types');
    }

    public function down(): void
    {
        // Data-only, non-destructive: keep the rows (licenses/provisions may reference them).
    }
};

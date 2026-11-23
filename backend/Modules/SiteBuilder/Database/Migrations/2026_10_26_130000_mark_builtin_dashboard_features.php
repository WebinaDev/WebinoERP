<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Preserve existing feature settings while adding the schema.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('webino_dashboard_features')) {
            return;
        }
        if (! Schema::hasColumn('webino_dashboard_features', 'is_builtin')) {
            Schema::table('webino_dashboard_features', function (Blueprint $table) {
                $table->boolean('is_builtin')->default(false)->after('is_addon');
            });
        }
        \Modules\Core\Support\Initialization\LegacyUpgradeQueue::record('site_types');
    }

    public function down(): void
    {
        // Non-destructive.
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops legacy external-panel columns from hosting settings (if present).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('core_hosting_settings')) {
            return;
        }

        $legacyPrefix = 'webino'.'server_';
        $toDrop = [];
        foreach (Schema::getColumnListing('core_hosting_settings') as $col) {
            if (str_starts_with($col, $legacyPrefix)) {
                $toDrop[] = $col;
            }
        }
        if ($toDrop === []) {
            return;
        }

        Schema::table('core_hosting_settings', function (Blueprint $table) use ($toDrop) {
            $table->dropColumn($toDrop);
        });
    }

    public function down(): void
    {
        // Intentionally empty — legacy external panel columns are not restored.
    }
};

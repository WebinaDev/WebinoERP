<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ensure database resources persist credentials in settings JSON (no schema change).
        // Placeholder migration kept for phase-4 documentation trail.
        if (! Schema::hasTable('platform_resources')) {
            return;
        }
        Schema::table('platform_resources', function (Blueprint $table) {
            if (! Schema::hasColumn('platform_resources', 'preview_url_template')) {
                $table->string('preview_url_template')->nullable()->after('settings');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('platform_resources')) {
            return;
        }
        Schema::table('platform_resources', function (Blueprint $table) {
            if (Schema::hasColumn('platform_resources', 'preview_url_template')) {
                $table->dropColumn('preview_url_template');
            }
        });
    }
};

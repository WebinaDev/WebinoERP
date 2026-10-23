<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webino_site_provisions', function (Blueprint $table) {
            if (! Schema::hasColumn('webino_site_provisions', 'site_status')) {
                // Public-facing site state on the tenant: active | coming_soon | suspended (null = never set)
                $table->string('site_status', 20)->nullable()->index();
                $table->string('site_status_reason', 191)->nullable();
                $table->timestamp('site_launch_at')->nullable();
                $table->string('site_status_updated_by', 20)->nullable();
                $table->timestamp('site_status_synced_at')->nullable();
                $table->text('site_status_sync_error')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('webino_site_provisions', function (Blueprint $table) {
            foreach (['site_status', 'site_status_reason', 'site_launch_at', 'site_status_updated_by', 'site_status_synced_at', 'site_status_sync_error'] as $c) {
                if (Schema::hasColumn('webino_site_provisions', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};

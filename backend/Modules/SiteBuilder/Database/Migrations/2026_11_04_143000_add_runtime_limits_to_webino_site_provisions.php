<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webino_site_provisions', function (Blueprint $table) {
            if (! Schema::hasColumn('webino_site_provisions', 'runtime_limits')) {
                // Per-site upload size + Docker mem/cpu limits (see TenantSiteLimits).
                $table->json('runtime_limits')->nullable()->after('wizard_payload');
            }
        });
    }

    public function down(): void
    {
        Schema::table('webino_site_provisions', function (Blueprint $table) {
            if (Schema::hasColumn('webino_site_provisions', 'runtime_limits')) {
                $table->dropColumn('runtime_limits');
            }
        });
    }
};

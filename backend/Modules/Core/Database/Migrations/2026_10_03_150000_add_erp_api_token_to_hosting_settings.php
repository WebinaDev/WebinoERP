<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('core_hosting_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('core_hosting_settings', 'erp_api_token')) {
                $table->text('erp_api_token')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('core_hosting_settings', function (Blueprint $table) {
            if (Schema::hasColumn('core_hosting_settings', 'erp_api_token')) {
                $table->dropColumn('erp_api_token');
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webino_site_provisions', function (Blueprint $table) {
            if (! Schema::hasColumn('webino_site_provisions', 'progress')) {
                $table->json('progress')->nullable()->after('error_log');
            }
        });
    }

    public function down(): void
    {
        Schema::table('webino_site_provisions', function (Blueprint $table) {
            if (Schema::hasColumn('webino_site_provisions', 'progress')) {
                $table->dropColumn('progress');
            }
        });
    }
};

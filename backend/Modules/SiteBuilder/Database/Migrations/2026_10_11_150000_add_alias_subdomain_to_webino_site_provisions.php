<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webino_site_provisions', function (Blueprint $table) {
            if (! Schema::hasColumn('webino_site_provisions', 'alias_subdomain')) {
                $table->string('alias_subdomain', 63)->default('contact')->after('subdomain');
            }
        });
    }

    public function down(): void
    {
        Schema::table('webino_site_provisions', function (Blueprint $table) {
            if (Schema::hasColumn('webino_site_provisions', 'alias_subdomain')) {
                $table->dropColumn('alias_subdomain');
            }
        });
    }
};

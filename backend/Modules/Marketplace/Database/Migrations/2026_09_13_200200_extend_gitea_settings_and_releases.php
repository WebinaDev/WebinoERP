<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_gitea_settings', function (Blueprint $table) {
            $table->string('ip_override', 100)->nullable()->after('org');
            $table->string('ip_scheme', 16)->default('auto')->after('ip_override');
        });

        Schema::table('marketplace_releases', function (Blueprint $table) {
            $table->string('tag_name', 100)->nullable()->after('version');
            $table->unsignedBigInteger('gitea_release_id')->nullable()->after('changelog');
            $table->string('package_path', 500)->nullable()->after('gitea_release_id');
            $table->string('package_source', 30)->nullable()->after('package_path');
        });

        Schema::table('marketplace_categories', function (Blueprint $table) {
            $table->integer('sort')->default(0)->after('slug');
            $table->string('status', 20)->default('active')->after('sort');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_categories', function (Blueprint $table) {
            $table->dropColumn(['sort', 'status']);
        });

        Schema::table('marketplace_releases', function (Blueprint $table) {
            $table->dropColumn(['tag_name', 'gitea_release_id', 'package_path', 'package_source']);
        });

        Schema::table('marketplace_gitea_settings', function (Blueprint $table) {
            $table->dropColumn(['ip_override', 'ip_scheme']);
        });
    }
};

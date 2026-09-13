<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_modules', function (Blueprint $table) {
            $table->string('distribution', 16)->default('git')->after('slug');
            $table->boolean('requires_license')->default(true)->after('price');
            $table->unsignedBigInteger('module_git_source_id')->nullable()->after('requires_license');
            $table->index('module_git_source_id');
        });

        Schema::table('marketplace_module_repos', function (Blueprint $table) {
            $table->string('provider', 32)->nullable()->after('gitea_repo');
            $table->string('default_branch', 100)->nullable()->after('provider');
            $table->string('latest_tag', 100)->nullable()->after('default_branch');
            $table->text('readme_excerpt')->nullable()->after('latest_tag');
        });

        Schema::table('marketplace_gitea_settings', function (Blueprint $table) {
            $table->string('provider', 32)->default('gitea')->after('id');
            $table->string('base_url')->nullable()->after('host');
            $table->unsignedBigInteger('platform_source_id')->nullable()->after('token');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_gitea_settings', function (Blueprint $table) {
            $table->dropColumn(['provider', 'base_url', 'platform_source_id']);
        });

        Schema::table('marketplace_module_repos', function (Blueprint $table) {
            $table->dropColumn(['provider', 'default_branch', 'latest_tag', 'readme_excerpt']);
        });

        Schema::table('marketplace_modules', function (Blueprint $table) {
            $table->dropColumn(['distribution', 'requires_license', 'module_git_source_id']);
        });
    }
};

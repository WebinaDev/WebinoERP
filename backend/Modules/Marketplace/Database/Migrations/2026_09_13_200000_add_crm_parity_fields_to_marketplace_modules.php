<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_modules', function (Blueprint $table) {
            $table->boolean('is_core')->default(false)->after('requires_license');
            $table->boolean('is_builtin')->default(false)->after('is_core');
            $table->boolean('is_free')->default(false)->after('is_builtin');
            $table->unsignedBigInteger('parent_module_id')->nullable()->after('category_id');
            $table->string('icon_url', 500)->nullable()->after('description');
            $table->string('detail_url', 500)->nullable()->after('icon_url');
            $table->string('settings_route', 255)->nullable()->after('detail_url');
            $table->string('settings_area', 20)->default('shop')->after('settings_route');
            $table->longText('readme_md')->nullable()->after('settings_area');
            $table->string('version', 50)->default('1.0.0')->after('readme_md');
            $table->string('currency', 10)->default('IRT')->after('price');
            $table->integer('sort')->default(0)->after('currency');
            $table->string('gitea_owner', 100)->nullable()->after('module_git_source_id');
            $table->string('gitea_repo', 100)->nullable()->after('gitea_owner');
            $table->unsignedBigInteger('gitea_repo_id')->nullable()->after('gitea_repo');
            $table->string('package_path', 500)->nullable()->after('gitea_repo_id');
            $table->string('package_source', 20)->default('local')->after('package_path');
            $table->unsignedBigInteger('latest_release_id')->nullable()->after('package_source');

            $table->index('parent_module_id');
            $table->index('is_core');
            $table->index('sort');
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_modules', function (Blueprint $table) {
            $table->dropIndex(['parent_module_id']);
            $table->dropIndex(['is_core']);
            $table->dropIndex(['sort']);
            $table->dropColumn([
                'is_core',
                'is_builtin',
                'is_free',
                'parent_module_id',
                'icon_url',
                'detail_url',
                'settings_route',
                'settings_area',
                'readme_md',
                'version',
                'currency',
                'sort',
                'gitea_owner',
                'gitea_repo',
                'gitea_repo_id',
                'package_path',
                'package_source',
                'latest_release_id',
            ]);
        });
    }
};

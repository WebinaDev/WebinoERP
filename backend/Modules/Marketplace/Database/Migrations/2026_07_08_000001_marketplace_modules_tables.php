<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_modules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('category_id')->nullable()->constrained('marketplace_categories')->nullOnDelete();
            $table->decimal('price', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('marketplace_module_repos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained('marketplace_modules')->cascadeOnDelete();
            $table->string('repo_url')->nullable();
            $table->string('repo_branch', 100)->default('main');
            $table->string('gitea_repo')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('marketplace_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained('marketplace_modules')->cascadeOnDelete();
            $table->string('version', 50);
            $table->text('changelog')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['module_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_releases');
        Schema::dropIfExists('marketplace_module_repos');
        Schema::dropIfExists('marketplace_modules');
    }
};

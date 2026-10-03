<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('builder_templates', function (Blueprint $table) {
            $table->id();
            // Dashboard FK to tenants. ERP company site stores BuilderSite::ID (1) with no FK.
            $table->unsignedBigInteger('tenant_id')->default(1);
            $table->string('kind', 32);
            $table->string('slug', 120)->nullable();
            $table->string('title')->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('priority')->default(0);
            $table->json('conditions')->nullable();
            $table->json('draft')->nullable();
            $table->json('published')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'kind', 'slug']);
        });

        Schema::create('builder_globals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->default(1);
            $table->json('draft')->nullable();
            $table->json('published')->nullable();
            $table->timestamps();
            $table->unique('tenant_id');
        });

        Schema::table('marketing_pages', function (Blueprint $table) {
            $table->json('builder_draft')->nullable();
            $table->json('builder_published')->nullable();
            $table->string('status', 24)->default('draft');
        });

        if (Schema::hasTable('marketing_pages')) {
            DB::table('marketing_pages')->where('published', true)->update(['status' => 'published']);
        }

        Schema::create('marketing_portfolio_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('marketing_portfolio_items', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('slug')->constrained('marketing_portfolio_categories')->nullOnDelete();
            $table->string('cover_url')->nullable();
            $table->string('result_metric')->nullable();
            $table->json('technologies')->nullable();
            $table->longText('case_study')->nullable();
            $table->boolean('featured')->default(false);
        });

        Schema::create('marketing_menus', function (Blueprint $table) {
            $table->id();
            $table->string('location', 32);
            $table->string('name');
            $table->boolean('published')->default(false);
            $table->timestamps();
            $table->unique('location');
        });

        Schema::create('marketing_menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained('marketing_menus')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('marketing_menu_items')->cascadeOnDelete();
            $table->string('label');
            $table->string('label_en')->nullable();
            $table->string('href')->default('/');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('published')->default(true);
            $table->timestamps();
        });

        Schema::create('marketing_forms', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('fields')->nullable();
            $table->string('success_message')->nullable();
            $table->boolean('published')->default(false);
            $table->timestamps();
        });

        Schema::create('marketing_form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('marketing_forms')->cascadeOnDelete();
            $table->json('payload');
            $table->string('locale', 8)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_form_submissions');
        Schema::dropIfExists('marketing_forms');
        Schema::dropIfExists('marketing_menu_items');
        Schema::dropIfExists('marketing_menus');

        Schema::table('marketing_portfolio_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn(['cover_url', 'result_metric', 'technologies', 'case_study', 'featured']);
        });

        Schema::dropIfExists('marketing_portfolio_categories');

        Schema::table('marketing_pages', function (Blueprint $table) {
            $table->dropColumn(['builder_draft', 'builder_published', 'status']);
        });

        Schema::dropIfExists('builder_globals');
        Schema::dropIfExists('builder_templates');
    }
};

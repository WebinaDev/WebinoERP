<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('logo_url')->nullable();
            $table->string('favicon_url')->nullable();
            $table->string('site_name')->default('وبینا');
            $table->string('active_theme_slug')->default('webina-corporate-v1');
            $table->json('branding')->nullable();
            $table->json('nav')->nullable();
            $table->json('home_blocks')->nullable();
            $table->json('social_links')->nullable();
            $table->timestamps();
        });

        Schema::create('marketing_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title_fa');
            $table->string('title_en')->nullable();
            $table->longText('body_fa')->nullable();
            $table->longText('body_en')->nullable();
            $table->string('template')->default('default');
            $table->boolean('published')->default(false);
            $table->unsignedBigInteger('wp_id')->nullable()->unique();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('marketing_blog_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('marketing_blog_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('marketing_blog_categories')->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->string('cover_url')->nullable();
            $table->string('status', 24)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('wp_id')->nullable()->unique();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('marketing_magazine_posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->string('cover_url')->nullable();
            $table->string('status', 24)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('wp_id')->nullable()->unique();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('marketing_academy_courses', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('cover_url')->nullable();
            $table->boolean('published')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('marketing_academy_lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('marketing_academy_courses')->cascadeOnDelete();
            $table->string('slug');
            $table->string('title');
            $table->longText('content')->nullable();
            $table->string('video_url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('published')->default(false);
            $table->timestamps();
            $table->unique(['course_id', 'slug']);
        });

        Schema::create('marketing_service_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('marketing_service_categories')->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('marketing_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('marketing_service_categories')->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->boolean('published')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('marketing_solution_industries', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('marketing_solution_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('industry_id')->constrained('marketing_solution_industries')->cascadeOnDelete();
            $table->string('slug');
            $table->string('title');
            $table->longText('body')->nullable();
            $table->boolean('published')->default(false);
            $table->timestamps();
            $table->unique(['industry_id', 'slug']);
        });

        Schema::create('marketing_portfolio_items', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('images')->nullable();
            $table->foreignId('service_id')->nullable()->constrained('marketing_services')->nullOnDelete();
            $table->foreignId('industry_id')->nullable()->constrained('marketing_solution_industries')->nullOnDelete();
            $table->string('client')->nullable();
            $table->boolean('published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('marketing_faq_items', function (Blueprint $table) {
            $table->id();
            $table->string('group')->nullable();
            $table->string('question');
            $table->text('answer');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('published')->default(true);
            $table->timestamps();
        });

        Schema::create('marketing_announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body')->nullable();
            $table->boolean('pinned')->default(false);
            $table->boolean('published')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        Schema::create('marketing_testimonials', function (Blueprint $table) {
            $table->id();
            $table->string('author');
            $table->string('role')->nullable();
            $table->string('company')->nullable();
            $table->text('quote');
            $table->unsignedTinyInteger('rating')->nullable();
            $table->string('avatar_url')->nullable();
            $table->boolean('published')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('marketing_team_members', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('role')->nullable();
            $table->text('bio')->nullable();
            $table->string('photo_url')->nullable();
            $table->json('social_links')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('published')->default(true);
            $table->timestamps();
        });

        Schema::create('marketing_media_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('marketing_media_folders')->nullOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('marketing_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->nullable()->constrained('marketing_media_folders')->nullOnDelete();
            $table->string('path');
            $table->string('mime', 128)->nullable();
            $table->string('alt')->nullable();
            $table->string('public_url')->nullable();
            $table->unsignedBigInteger('wp_id')->nullable()->unique();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('marketing_downloads', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('file_id')->nullable()->constrained('marketing_media')->nullOnDelete();
            $table->string('category')->nullable();
            $table->boolean('published')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_downloads');
        Schema::dropIfExists('marketing_media');
        Schema::dropIfExists('marketing_media_folders');
        Schema::dropIfExists('marketing_team_members');
        Schema::dropIfExists('marketing_testimonials');
        Schema::dropIfExists('marketing_announcements');
        Schema::dropIfExists('marketing_faq_items');
        Schema::dropIfExists('marketing_portfolio_items');
        Schema::dropIfExists('marketing_solution_pages');
        Schema::dropIfExists('marketing_solution_industries');
        Schema::dropIfExists('marketing_services');
        Schema::dropIfExists('marketing_service_categories');
        Schema::dropIfExists('marketing_academy_lessons');
        Schema::dropIfExists('marketing_academy_courses');
        Schema::dropIfExists('marketing_magazine_posts');
        Schema::dropIfExists('marketing_blog_posts');
        Schema::dropIfExists('marketing_blog_categories');
        Schema::dropIfExists('marketing_pages');
        Schema::dropIfExists('marketing_site_settings');
    }
};

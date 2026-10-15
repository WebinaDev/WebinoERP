<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Page builder phases 2–6 (synced from WebinoDashboard): revisions, library, form actions/files/submissions,
 * role policies, page lifecycle (schedule, language, SEO, preview), A/B tests, CMS collections and slug redirects.
 * The ERP company site is a single site: tenant_id defaults to BuilderSite::ID (1) and has no tenants FK.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_pages', function (Blueprint $table) {
            if (! Schema::hasColumn('marketing_pages', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->default(1)->index();
            }
            if (! Schema::hasColumn('marketing_pages', 'seo')) {
                $table->json('seo')->nullable();
            }
            if (! Schema::hasColumn('marketing_pages', 'builder_publish_at')) {
                $table->timestamp('builder_publish_at')->nullable();
                $table->timestamp('builder_unpublish_at')->nullable();
            }
            if (! Schema::hasColumn('marketing_pages', 'lang')) {
                $table->string('lang', 10)->nullable();
                $table->string('translation_group', 36)->nullable()->index();
            }
        });

        Schema::table('marketing_blog_posts', function (Blueprint $table) {
            if (! Schema::hasColumn('marketing_blog_posts', 'builder_draft')) {
                $table->json('builder_draft')->nullable();
                $table->json('builder_published')->nullable();
            }
        });

        Schema::create('builder_revisions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->default(1)->index();
            $table->string('target_type', 20);
            $table->unsignedBigInteger('target_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('reason', 16)->default('save');
            $table->json('document');
            $table->unsignedInteger('size')->default(0);
            $table->timestamps();
            $table->index(['tenant_id', 'target_type', 'target_id', 'id'], 'builder_revisions_target_idx');
        });

        Schema::create('builder_library_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->default(1)->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('kind', 16)->default('section');
            $table->string('title', 160);
            $table->string('category', 80)->nullable();
            $table->json('document');
            $table->timestamps();
            $table->index(['tenant_id', 'kind']);
        });

        Schema::create('builder_form_submissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->default(1)->index();
            $table->string('form_key', 100)->default('contact');
            $table->string('page_uri', 512)->nullable();
            $table->json('payload');
            $table->string('ip_hash', 64)->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('builder_form_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->default(1)->index();
            $table->string('form_key', 100);
            $table->json('actions');
            $table->timestamps();
            $table->unique(['tenant_id', 'form_key']);
        });

        Schema::create('builder_form_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->default(1)->index();
            $table->string('form_key', 100);
            $table->string('token_hash', 64)->unique();
            $table->string('path', 255);
            $table->string('original_name', 180);
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->foreignId('submission_id')->nullable()->constrained('builder_form_submissions')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('builder_role_policies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->default(1);
            $table->string('role', 40);
            $table->boolean('can_edit')->default(true);
            $table->boolean('can_publish')->default(true);
            $table->boolean('can_design')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'role']);
        });

        Schema::create('builder_ab_tests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->default(1);
            $table->foreignId('page_id')->constrained('marketing_pages')->cascadeOnDelete();
            $table->foreignId('variant_page_id')->constrained('marketing_pages')->cascadeOnDelete();
            $table->unsignedTinyInteger('split')->default(50);
            $table->string('status', 20)->default('running');
            $table->unsignedInteger('views_a')->default(0);
            $table->unsignedInteger('views_b')->default(0);
            $table->unsignedInteger('conversions_a')->default(0);
            $table->unsignedInteger('conversions_b')->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'page_id']);
        });

        Schema::create('builder_collections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->default(1);
            $table->string('slug', 80);
            $table->string('name', 120);
            $table->json('fields');
            $table->timestamps();
            $table->unique(['tenant_id', 'slug']);
        });

        Schema::create('builder_collection_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->default(1);
            $table->foreignId('collection_id')->constrained('builder_collections')->cascadeOnDelete();
            $table->string('slug', 120);
            $table->string('title', 200);
            $table->json('data');
            $table->boolean('published')->default(true);
            $table->integer('sort')->default(0);
            $table->timestamps();
            $table->unique(['collection_id', 'slug']);
            $table->index(['tenant_id', 'collection_id', 'published']);
        });

        Schema::create('seo_redirects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->default(1);
            $table->string('from_path', 512);
            $table->string('to_path', 512);
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('enabled')->default(true);
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'from_path']);
            $table->index(['tenant_id', 'enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_redirects');
        Schema::dropIfExists('builder_collection_items');
        Schema::dropIfExists('builder_collections');
        Schema::dropIfExists('builder_ab_tests');
        Schema::dropIfExists('builder_role_policies');
        Schema::dropIfExists('builder_form_files');
        Schema::dropIfExists('builder_form_settings');
        Schema::dropIfExists('builder_form_submissions');
        Schema::dropIfExists('builder_library_items');
        Schema::dropIfExists('builder_revisions');
        Schema::table('marketing_blog_posts', function (Blueprint $table) {
            $table->dropColumn(['builder_draft', 'builder_published']);
        });
        Schema::table('marketing_pages', function (Blueprint $table) {
            $table->dropIndex(['translation_group']);
            $table->dropIndex(['tenant_id']);
            $table->dropColumn(['tenant_id', 'seo', 'builder_publish_at', 'builder_unpublish_at', 'lang', 'translation_group']);
        });
    }
};

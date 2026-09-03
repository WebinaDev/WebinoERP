<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_content_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_type', 64)->default('');
            $table->string('target_type', 32)->default('');
            $table->unsignedBigInteger('target_id')->default(0);
            $table->longText('payload')->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('provider', 32)->default('');
            $table->string('model', 128)->default('');
            $table->integer('tokens_in')->default(0);
            $table->integer('tokens_out')->default(0);
            $table->decimal('cost_toman', 16, 4)->default(0);
            $table->text('error_message')->nullable();
            $table->text('result_summary')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->index('status');
            $table->index('job_type');
            $table->index(['target_type', 'target_id']);
        });

        Schema::create('ai_content_calendar', function (Blueprint $table) {
            $table->id();
            $table->date('slot_date');
            $table->string('content_type', 20)->default('blog');
            $table->string('topic', 500)->default('');
            $table->string('focus_keyword', 255)->default('');
            $table->text('secondary_keywords')->nullable();
            $table->unsignedBigInteger('category_id')->default(0);
            $table->unsignedBigInteger('product_id')->default(0);
            $table->string('status', 20)->default('planned');
            $table->unsignedBigInteger('job_id')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('slot_date');
            $table->index('status');
            $table->index('content_type');
        });

        Schema::create('ai_content_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku', 100)->nullable();
            $table->json('missing')->nullable();
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();
            $table->string('status', 20)->default('incomplete');
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_content_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('status', 20)->default('draft');
            $table->string('url', 500)->nullable();
            $table->text('page_prompt')->nullable();
            $table->boolean('has_elementor')->default(false);
            $table->string('elementor_url', 500)->nullable();
            $table->longText('content')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_content_proposals', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 32)->default('');
            $table->unsignedBigInteger('product_id')->default(0);
            $table->string('product_name')->default('');
            $table->json('current_json')->nullable();
            $table->json('proposed_json')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();
            $table->unique(['kind', 'product_id']);
            $table->index('status');
            $table->index('product_id');
        });

        Schema::create('ai_content_attr_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_cat_id');
            $table->string('category_name')->nullable();
            $table->json('attribute_ids')->nullable();
            $table->json('labels')->nullable();
            $table->json('draft')->nullable();
            $table->timestamps();
            $table->unique('product_cat_id');
        });

        Schema::create('ai_content_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64)->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_content_suggestions', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 32);
            $table->json('suggestions')->nullable();
            $table->timestamps();
            $table->unique('kind');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_content_suggestions');
        Schema::dropIfExists('ai_content_settings');
        Schema::dropIfExists('ai_content_attr_templates');
        Schema::dropIfExists('ai_content_proposals');
        Schema::dropIfExists('ai_content_pages');
        Schema::dropIfExists('ai_content_products');
        Schema::dropIfExists('ai_content_calendar');
        Schema::dropIfExists('ai_content_jobs');
    }
};

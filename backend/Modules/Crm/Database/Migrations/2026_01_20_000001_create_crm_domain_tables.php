<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('color', 7)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('crm_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('color', 7);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('crm_pipelines', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('crm_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pipeline_id')->constrained('crm_pipelines')->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order');
            $table->unsignedTinyInteger('probability')->default(0);
            $table->string('color', 7);
            $table->boolean('is_closed')->default(false);
            $table->boolean('is_won')->default(false);
            $table->timestamps();
            $table->unique(['pipeline_id', 'sort_order']);
        });

        Schema::create('crm_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('account_code', 50)->nullable()->unique();
            $table->string('website', 200)->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('crm_accounts')->nullOnDelete();
            $table->string('type', 20);
            $table->string('tax_id', 50)->nullable();
            $table->string('industry', 50)->nullable();
            $table->unsignedInteger('employees_count')->nullable();
            $table->decimal('annual_revenue', 15, 2)->nullable();
            $table->json('billing_address')->nullable();
            $table->json('shipping_address')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('crm_leads', function (Blueprint $table) {
            $table->id();
            $table->string('topic', 255);
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('company', 150)->nullable();
            $table->string('job_title', 100)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('mobile', 20);
            $table->string('phone', 20)->nullable();
            $table->foreignId('source_id')->nullable()->constrained('crm_sources')->nullOnDelete();
            $table->foreignId('status_id')->constrained('crm_statuses');
            $table->string('industry', 50)->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->integer('lead_score')->default(0);
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('description')->nullable();
            $table->json('address_json')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('converted_to_account_id')->nullable()->constrained('crm_accounts')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('crm_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('crm_accounts')->cascadeOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 150)->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('job_title', 100)->nullable();
            $table->foreignId('reports_to')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $table->string('decision_role', 20)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->json('social_profiles')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('crm_deals', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->foreignId('account_id')->constrained('crm_accounts')->restrictOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $table->foreignId('pipeline_id')->constrained('crm_pipelines')->restrictOnDelete();
            $table->foreignId('stage_id')->constrained('crm_stages')->restrictOnDelete();
            $table->decimal('amount', 15, 2)->default(0);
            $table->unsignedTinyInteger('probability')->default(0);
            $table->date('close_date')->nullable();
            $table->string('type', 20)->default('New Business');
            $table->text('loss_reason')->nullable();
            $table->string('campaign_source', 255)->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('description')->nullable();
            $table->timestamp('won_at')->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('crm_deal_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained('crm_deals')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('product_name', 255);
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('tax', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_activities', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('subject', 255);
            $table->text('description')->nullable();
            $table->string('related_model', 255);
            $table->unsignedBigInteger('related_id');
            $table->string('outcome', 100)->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->date('due_date')->nullable();
            $table->string('priority', 10)->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['related_model', 'related_id']);
        });

        Schema::create('crm_module_settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();
            $table->string('group', 50)->default('general');
            $table->timestamps();
        });

        Schema::create('crm_consultations', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->foreignId('account_id')->nullable()->constrained('crm_accounts')->nullOnDelete();
            $table->string('status', 50)->default('new');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_consultations');
        Schema::dropIfExists('crm_module_settings');
        Schema::dropIfExists('crm_activities');
        Schema::dropIfExists('crm_deal_products');
        Schema::dropIfExists('crm_deals');
        Schema::dropIfExists('crm_contacts');
        Schema::dropIfExists('crm_leads');
        Schema::dropIfExists('crm_accounts');
        Schema::dropIfExists('crm_stages');
        Schema::dropIfExists('crm_pipelines');
        Schema::dropIfExists('crm_statuses');
        Schema::dropIfExists('crm_sources');
    }
};

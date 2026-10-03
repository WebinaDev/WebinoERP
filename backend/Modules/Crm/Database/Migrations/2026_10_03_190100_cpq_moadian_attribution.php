<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_catalog_products', function (Blueprint $table) {
            $table->string('category', 80)->nullable();
        });

        Schema::create('crm_product_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('crm_catalog_products')->cascadeOnDelete();
            $table->foreignId('related_product_id')->constrained('crm_catalog_products')->cascadeOnDelete();
            $table->string('kind', 20);
            $table->decimal('min_qty', 12, 2)->default(1);
            $table->timestamps();
        });

        Schema::create('crm_discount_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('crm_discount_nodes')->cascadeOnDelete();
            $table->string('scope', 20);
            $table->string('scope_key')->nullable();
            $table->string('name');
            $table->decimal('percent', 8, 4)->default(0);
            $table->decimal('min_amount', 18, 2)->nullable();
            $table->boolean('stackable')->default(true);
            $table->timestamps();
        });

        Schema::create('crm_quote_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained('crm_deals')->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->decimal('discount_percent', 8, 4)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->decimal('threshold_percent', 8, 4)->default(0);
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_touchpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->nullable()->constrained('crm_leads')->nullOnDelete();
            $table->foreignId('deal_id')->nullable()->constrained('crm_deals')->nullOnDelete();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('channel', 32)->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();
            $table->index(['utm_campaign', 'deal_id']);
        });

        Schema::create('crm_campaign_spends', function (Blueprint $table) {
            $table->id();
            $table->string('campaign_key');
            $table->string('name')->nullable();
            $table->decimal('spent', 18, 2)->default(0);
            $table->string('currency_code', 8)->default('IRR');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamps();
            $table->index('campaign_key');
        });

        Schema::table('crm_einvoices', function (Blueprint $table) {
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->string('reference_number')->nullable();
            $table->boolean('sandbox')->default(false);
        });

        Schema::table('crm_content_items', function (Blueprint $table) {
            $table->string('publish_status', 20)->default('idle');
            $table->string('external_post_id')->nullable();
            $table->text('publish_error')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('image_url')->nullable();
            $table->unsignedSmallInteger('publish_attempts')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('crm_content_items', function (Blueprint $table) {
            $table->dropColumn(['publish_status', 'external_post_id', 'publish_error', 'published_at', 'image_url', 'publish_attempts']);
        });
        Schema::table('crm_einvoices', function (Blueprint $table) {
            $table->dropColumn(['attempts', 'next_retry_at', 'reference_number', 'sandbox']);
        });
        Schema::dropIfExists('crm_campaign_spends');
        Schema::dropIfExists('crm_touchpoints');
        Schema::dropIfExists('crm_quote_approvals');
        Schema::dropIfExists('crm_discount_nodes');
        Schema::dropIfExists('crm_product_rules');
        Schema::table('crm_catalog_products', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};

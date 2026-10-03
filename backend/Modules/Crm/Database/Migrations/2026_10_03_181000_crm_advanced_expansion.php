<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('national_id', 20)->nullable();
            $table->string('economic_code', 20)->nullable();
            $table->string('currency_code', 8)->default('IRR');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('crm_company_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('crm_companies')->cascadeOnDelete();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->unique(['user_id', 'company_id']);
        });

        Schema::table('crm_deals', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->constrained('crm_companies')->nullOnDelete();
            $table->string('currency_code', 8)->default('IRR');
        });

        Schema::create('crm_currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 8)->unique();
            $table->string('name');
            $table->string('symbol', 8)->nullable();
            $table->unsignedTinyInteger('decimal_places')->default(0);
            $table->boolean('is_base')->default(false);
            $table->timestamps();
        });

        Schema::create('crm_fx_rates', function (Blueprint $table) {
            $table->id();
            $table->string('base_code', 8);
            $table->string('quote_code', 8);
            $table->decimal('rate', 18, 8);
            $table->date('effective_on');
            $table->timestamps();
            $table->index(['base_code', 'quote_code', 'effective_on']);
        });

        $now = now();
        DB::table('crm_currencies')->insert([
            ['code' => 'IRR', 'name' => 'Iranian Rial', 'symbol' => '﷼', 'decimal_places' => 0, 'is_base' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'IRT', 'name' => 'Toman', 'symbol' => 'ت', 'decimal_places' => 0, 'is_base' => false, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_base' => false, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2, 'is_base' => false, 'created_at' => $now, 'updated_at' => $now],
        ]);

        Schema::create('crm_catalog_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('crm_companies')->nullOnDelete();
            $table->string('sku', 64)->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('unit', 32)->default('unit');
            $table->decimal('tax_percent', 5, 2)->default(10);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('crm_price_books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('crm_companies')->nullOnDelete();
            $table->string('name');
            $table->string('currency_code', 8)->default('IRR');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('crm_price_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_book_id')->constrained('crm_price_books')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('crm_catalog_products')->cascadeOnDelete();
            $table->unsignedInteger('min_qty')->default(1);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('crm_deal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deal_id')->constrained('crm_deals')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('crm_catalog_products')->nullOnDelete();
            $table->decimal('qty', 12, 2)->default(1);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->decimal('line_total', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('crm_lead_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('crm_companies')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->json('fields')->nullable();
            $table->string('landing_url')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('notify_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('crm_attributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('crm_leads')->cascadeOnDelete();
            $table->foreignId('form_id')->nullable()->constrained('crm_lead_forms')->nullOnDelete();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('referrer')->nullable();
            $table->string('landing_path')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_envelopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('crm_companies')->nullOnDelete();
            $table->foreignId('deal_id')->nullable()->constrained('crm_deals')->nullOnDelete();
            $table->string('title');
            $table->longText('body')->nullable();
            $table->string('document_hash', 128)->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamps();
        });

        Schema::create('crm_signers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('envelope_id')->constrained('crm_envelopes')->cascadeOnDelete();
            $table->string('name');
            $table->string('national_id', 20)->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('role', 40)->default('signer');
            $table->string('otp_hash')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->string('signature_hash', 128)->nullable();
            $table->timestamps();
        });

        Schema::create('crm_einvoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('crm_companies')->nullOnDelete();
            $table->foreignId('deal_id')->nullable()->constrained('crm_deals')->nullOnDelete();
            $table->unsignedTinyInteger('invoice_type')->default(1);
            $table->unsignedTinyInteger('pattern')->default(1);
            $table->string('serial', 32);
            $table->string('taxid', 32)->nullable();
            $table->string('seller_economic_code', 20)->nullable();
            $table->string('seller_national_id', 20)->nullable();
            $table->string('buyer_economic_code', 20)->nullable();
            $table->string('buyer_national_id', 20)->nullable();
            $table->string('buyer_type', 20)->default('legal');
            $table->string('currency_code', 8)->default('IRR');
            $table->decimal('total', 18, 2)->default(0);
            $table->decimal('vat', 18, 2)->default(0);
            $table->string('status', 20)->default('draft');
            $table->json('document');
            $table->json('provider_response')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_content_calendars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained('crm_accounts')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('crm_companies')->nullOnDelete();
            $table->string('kind', 20)->default('social');
            $table->string('network', 32)->default('instagram');
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('crm_content_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calendar_id')->constrained('crm_content_calendars')->cascadeOnDelete();
            $table->string('title');
            $table->longText('body')->nullable();
            $table->string('status', 20)->default('idea');
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('publish_start')->nullable();
            $table->timestamp('publish_end')->nullable();
            $table->timestamp('remind_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_content_items');
        Schema::dropIfExists('crm_content_calendars');
        Schema::dropIfExists('crm_einvoices');
        Schema::dropIfExists('crm_signers');
        Schema::dropIfExists('crm_envelopes');
        Schema::dropIfExists('crm_attributions');
        Schema::dropIfExists('crm_lead_forms');
        Schema::dropIfExists('crm_deal_lines');
        Schema::dropIfExists('crm_price_items');
        Schema::dropIfExists('crm_price_books');
        Schema::dropIfExists('crm_catalog_products');
        Schema::dropIfExists('crm_fx_rates');
        Schema::dropIfExists('crm_currencies');
        Schema::table('crm_deals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
            $table->dropColumn('currency_code');
        });
        Schema::dropIfExists('crm_company_users');
        Schema::dropIfExists('crm_companies');
    }
};

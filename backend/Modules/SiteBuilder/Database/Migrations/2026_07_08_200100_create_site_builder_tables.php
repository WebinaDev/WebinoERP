<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webino_business_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name_fa', 191);
            $table->string('name_en', 191);
            $table->string('icon', 64)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('webino_business_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('webino_business_categories')->cascadeOnDelete();
            $table->string('slug', 64);
            $table->string('name_fa', 191);
            $table->string('name_en', 191);
            $table->text('description_fa')->nullable();
            $table->text('description_en')->nullable();
            $table->string('theme_preset', 64)->nullable();
            $table->json('default_module_slugs')->nullable();
            $table->json('nav_preset')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['category_id', 'slug']);
        });

        Schema::create('webino_dashboard_features', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name_fa', 191);
            $table->string('name_en', 191);
            $table->string('module_slug', 64)->nullable();
            $table->boolean('is_addon')->default(false);
            $table->boolean('default_enabled')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('webino_business_type_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_type_id')->constrained('webino_business_types')->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained('webino_dashboard_features')->cascadeOnDelete();
            $table->boolean('is_required')->default(false);
            $table->boolean('default_selected')->default(true);
            $table->timestamps();

            $table->unique(['business_type_id', 'feature_id']);
        });

        Schema::create('webino_packages', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 128)->unique();
            $table->string('name_fa', 191);
            $table->string('name_en', 191);
            $table->foreignId('business_type_id')->constrained('webino_business_types')->cascadeOnDelete();
            $table->unsignedBigInteger('price')->default(0);
            $table->string('billing_period', 32)->default('yearly');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('webino_package_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('webino_packages')->cascadeOnDelete();
            $table->foreignId('feature_id')->constrained('webino_dashboard_features')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['package_id', 'feature_id']);
        });

        Schema::create('webino_site_provisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_account_id')->nullable()->constrained('crm_accounts')->nullOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('webino_packages')->nullOnDelete();
            $table->foreignId('license_id')->nullable()->constrained('core_licenses')->nullOnDelete();
            $table->string('slug', 64);
            $table->string('domain', 255);
            $table->string('subdomain', 128)->nullable();
            $table->boolean('uses_custom_domain')->default(false);
            $table->string('status', 32)->default('draft');
            $table->json('wizard_payload')->nullable();
            $table->text('error_log')->nullable();
            $table->string('server_host_id', 64)->nullable();
            $table->string('provision_token', 128)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('launched_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webino_site_provisions');
        Schema::dropIfExists('webino_package_features');
        Schema::dropIfExists('webino_packages');
        Schema::dropIfExists('webino_business_type_features');
        Schema::dropIfExists('webino_dashboard_features');
        Schema::dropIfExists('webino_business_types');
        Schema::dropIfExists('webino_business_categories');
    }
};

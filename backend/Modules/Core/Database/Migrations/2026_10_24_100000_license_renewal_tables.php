<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Self-service license renewal: per product/plan renewal price list and renewal orders
 * (one row per checkout attempt, settled via the payment gateway side effect).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('core_license_renewal_prices')) {
            Schema::create('core_license_renewal_prices', function (Blueprint $table) {
                $table->id();
                $table->string('product', 64)->default('webino');
                $table->string('plan', 64)->nullable();
                $table->string('plan_name')->nullable();
                $table->unsignedSmallInteger('months');
                $table->unsignedBigInteger('price_irr');
                $table->decimal('discount_percent', 5, 2)->nullable();
                $table->string('label')->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
                $table->index(['product', 'plan', 'is_active']);
            });
        }

        if (! Schema::hasTable('core_license_renewals')) {
            Schema::create('core_license_renewals', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('license_id')->index();
                $table->unsignedBigInteger('price_id')->nullable();
                $table->unsignedBigInteger('crm_account_id')->nullable()->index();
                $table->unsignedSmallInteger('months');
                $table->unsignedBigInteger('price_irr');
                $table->decimal('discount_percent', 5, 2)->nullable();
                $table->unsignedBigInteger('amount_irr');
                $table->string('status', 20)->default('pending');
                $table->string('return_url', 1000)->nullable();
                $table->string('payment_id', 64)->nullable();
                $table->unsignedBigInteger('sales_invoice_id')->nullable();
                $table->timestamp('previous_expires_at')->nullable();
                $table->timestamp('new_expires_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('core_license_renewals');
        Schema::dropIfExists('core_license_renewal_prices');
    }
};

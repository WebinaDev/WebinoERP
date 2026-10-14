<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Storefront (builder shop widgets) on top of the sales catalog. Additive only. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sales_catalog_items', 'meta')) {
            Schema::table('sales_catalog_items', fn (Blueprint $t) => $t->json('meta')->nullable());
        }
        Schema::create('sales_carts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $t->string('coupon_code', 40)->nullable();
            $t->timestamps();
        });
        Schema::create('sales_cart_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cart_id')->constrained('sales_carts')->cascadeOnDelete();
            $t->foreignId('product_id')->constrained('sales_catalog_items')->cascadeOnDelete();
            $t->unsignedInteger('quantity')->default(1);
            $t->boolean('saved_for_later')->default(false);
            $t->timestamps();
            $t->unique(['cart_id', 'product_id']);
        });
        Schema::create('sales_coupons', function (Blueprint $t) {
            $t->id();
            $t->string('code', 40)->unique();
            $t->string('type', 10)->default('percent');
            $t->decimal('value', 15, 2)->default(0);
            $t->decimal('min_total', 15, 2)->default(0);
            $t->unsignedInteger('usage_limit')->nullable();
            $t->unsignedInteger('used')->default(0);
            $t->timestamp('expires_at')->nullable();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('sales_storefront_orders', function (Blueprint $t) {
            $t->id();
            $t->string('number', 30)->unique();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status', 30)->default('pending_payment');
            $t->string('payment_method', 30)->default('manual');
            $t->json('items');
            $t->json('address')->nullable();
            $t->string('phone', 30)->nullable();
            $t->decimal('subtotal', 15, 2)->default(0);
            $t->decimal('discount', 15, 2)->default(0);
            $t->decimal('shipping', 15, 2)->default(0);
            $t->decimal('total', 15, 2)->default(0);
            $t->string('coupon_code', 40)->nullable();
            $t->string('tracking_code', 60)->nullable();
            $t->foreignId('invoice_id')->nullable()->constrained('sales_invoices')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('sales_product_reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained('sales_catalog_items')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('author_name', 80)->nullable();
            $t->unsignedTinyInteger('rating')->default(5);
            $t->text('body')->nullable();
            $t->string('status', 20)->default('pending');
            $t->unsignedInteger('likes')->default(0);
            $t->unsignedInteger('reports')->default(0);
            $t->timestamps();
        });
        Schema::create('sales_product_questions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained('sales_catalog_items')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('body');
            $t->text('answer')->nullable();
            $t->string('status', 20)->default('pending');
            $t->timestamps();
        });
        Schema::create('sales_product_favorites', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('product_id')->constrained('sales_catalog_items')->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['user_id', 'product_id']);
        });
    }

    public function down(): void
    {
        foreach (['sales_product_favorites', 'sales_product_questions', 'sales_product_reviews', 'sales_storefront_orders', 'sales_coupons', 'sales_cart_items', 'sales_carts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

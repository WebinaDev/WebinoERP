<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Storefront purchase flow: product variants in the cart, stepped checkout session, order meta,
 * gift cards. Additive: new columns/tables only. The old cart line unique key (cart, product) is
 * widened to (cart, product, variant_key) so two variants of one product can sit in one cart.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sales_cart_items', 'variant_key')) {
            Schema::table('sales_cart_items', function (Blueprint $t) {
                $t->string('variant_key', 40)->default('');
            });
            Schema::table('sales_cart_items', function (Blueprint $t) {
                $t->unique(['cart_id', 'product_id', 'variant_key'], 'sales_cart_items_line_unique');
            });
            Schema::table('sales_cart_items', function (Blueprint $t) {
                $t->dropUnique(['cart_id', 'product_id']);
            });
        }
        if (! Schema::hasColumn('sales_carts', 'meta')) {
            Schema::table('sales_carts', fn (Blueprint $t) => $t->json('meta')->nullable());
        }
        if (! Schema::hasColumn('sales_storefront_orders', 'meta')) {
            Schema::table('sales_storefront_orders', fn (Blueprint $t) => $t->json('meta')->nullable());
        }
        if (! Schema::hasTable('sales_gift_cards')) {
            Schema::create('sales_gift_cards', function (Blueprint $t) {
                $t->id();
                $t->string('code', 40)->unique();
                $t->unsignedBigInteger('initial_minor')->default(0);
                $t->unsignedBigInteger('balance_minor')->default(0);
                $t->string('status', 20)->default('active');
                $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $t->string('recipient_name', 120)->nullable();
                $t->string('recipient_phone', 30)->nullable();
                $t->string('recipient_email', 191)->nullable();
                $t->string('message', 500)->nullable();
                $t->string('note', 500)->nullable();
                $t->timestamp('expires_at')->nullable();
                $t->unsignedBigInteger('issued_by')->nullable();
                $t->json('meta')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('sales_gift_card_transactions')) {
            Schema::create('sales_gift_card_transactions', function (Blueprint $t) {
                $t->id();
                $t->foreignId('gift_card_id')->constrained('sales_gift_cards')->cascadeOnDelete();
                $t->unsignedBigInteger('order_id')->nullable()->index();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->string('type', 20);
                $t->bigInteger('amount_minor');
                $t->unsignedBigInteger('balance_after_minor');
                $t->string('note', 500)->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_gift_card_transactions');
        Schema::dropIfExists('sales_gift_cards');
    }
};

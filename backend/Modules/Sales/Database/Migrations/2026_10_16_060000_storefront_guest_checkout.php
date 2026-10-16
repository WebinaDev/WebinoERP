<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Guest carts (token) + customer fields on storefront orders + shop settings. Additive only. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sales_carts', 'token')) {
            Schema::table('sales_carts', function (Blueprint $t) {
                $t->string('token', 64)->nullable()->unique();
            });
            Schema::table('sales_carts', function (Blueprint $t) {
                $t->unsignedBigInteger('user_id')->nullable()->change();
            });
        }
        Schema::table('sales_storefront_orders', function (Blueprint $t) {
            if (! Schema::hasColumn('sales_storefront_orders', 'customer_name')) {
                $t->string('customer_name', 120)->nullable();
                $t->string('customer_email', 191)->nullable();
                $t->string('guest_token', 64)->nullable()->index();
                $t->string('payment_mode', 20)->nullable();
                $t->timestamp('paid_at')->nullable();
            }
        });
        if (! Schema::hasTable('sales_storefront_settings')) {
            Schema::create('sales_storefront_settings', function (Blueprint $t) {
                $t->id();
                $t->string('key', 80)->unique();
                $t->json('value')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_storefront_settings');
        Schema::table('sales_storefront_orders', fn (Blueprint $t) => $t->dropColumn(['customer_name', 'customer_email', 'guest_token', 'payment_mode', 'paid_at']));
        Schema::table('sales_carts', fn (Blueprint $t) => $t->dropColumn('token'));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Opt-in flag: list the coupon in the cart/checkout "coupons you can use" chooser. Additive only. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('sales_coupons', 'show_in_cart')) {
            Schema::table('sales_coupons', function (Blueprint $t) {
                $t->boolean('show_in_cart')->default(false);
                $t->string('description', 191)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sales_coupons', 'show_in_cart')) {
            Schema::table('sales_coupons', function (Blueprint $t) {
                $t->dropColumn(['show_in_cart', 'description']);
            });
        }
    }
};

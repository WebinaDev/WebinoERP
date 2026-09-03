<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_campaigns', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_campaigns', 'channel')) {
                $table->string('channel', 50)->nullable()->after('status');
            }
        });

        Schema::table('sales_catalog_items', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_catalog_items', 'type')) {
                $table->string('type', 30)->default('product')->after('status');
            }
            if (! Schema::hasColumn('sales_catalog_items', 'meta')) {
                $table->json('meta')->nullable()->after('type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_campaigns', function (Blueprint $table) {
            if (Schema::hasColumn('sales_campaigns', 'channel')) {
                $table->dropColumn('channel');
            }
        });

        Schema::table('sales_catalog_items', function (Blueprint $table) {
            $cols = array_filter(['type', 'meta'], fn ($c) => Schema::hasColumn('sales_catalog_items', $c));
            if ($cols !== []) {
                $table->dropColumn($cols);
            }
        });
    }
};

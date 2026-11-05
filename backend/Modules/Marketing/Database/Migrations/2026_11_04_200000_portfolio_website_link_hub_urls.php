<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Dedicated website + LinkHub URLs on portfolio case studies (admin fields; public detail). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_portfolio_items', function (Blueprint $table) {
            if (! Schema::hasColumn('marketing_portfolio_items', 'website_url')) {
                $table->string('website_url', 500)->nullable();
            }
            if (! Schema::hasColumn('marketing_portfolio_items', 'link_hub_url')) {
                $table->string('link_hub_url', 500)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketing_portfolio_items', function (Blueprint $table) {
            foreach (['website_url', 'link_hub_url'] as $col) {
                if (Schema::hasColumn('marketing_portfolio_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Portfolio case-study fields (challenge / solution / results) and manual ordering. Schema only, no data. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_portfolio_items', function (Blueprint $table) {
            foreach (['challenge', 'solution', 'results'] as $col) {
                if (! Schema::hasColumn('marketing_portfolio_items', $col)) {
                    $table->text($col)->nullable();
                }
            }
            if (! Schema::hasColumn('marketing_portfolio_items', 'sort_order')) {
                $table->integer('sort_order')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketing_portfolio_items', function (Blueprint $table) {
            $table->dropColumn(['challenge', 'solution', 'results', 'sort_order']);
        });
    }
};

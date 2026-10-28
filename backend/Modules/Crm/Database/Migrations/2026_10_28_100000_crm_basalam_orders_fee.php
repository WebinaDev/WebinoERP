<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('crm_basalam_orders') && ! Schema::hasColumn('crm_basalam_orders', 'fee')) {
            Schema::table('crm_basalam_orders', function (Blueprint $t) {
                $t->decimal('fee', 18, 2)->nullable()->after('total'); // Basalam commission/fee in IRR, optional
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('crm_basalam_orders', 'fee')) {
            Schema::table('crm_basalam_orders', fn (Blueprint $t) => $t->dropColumn('fee'));
        }
    }
};

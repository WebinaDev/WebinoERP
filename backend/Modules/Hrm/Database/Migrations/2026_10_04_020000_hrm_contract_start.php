<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hrm_employees')) {
            return;
        }
        Schema::table('hrm_employees', function (Blueprint $table) {
            if (! Schema::hasColumn('hrm_employees', 'contract_start_date')) {
                $table->date('contract_start_date')->nullable()->after('hire_date');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('hrm_employees') && Schema::hasColumn('hrm_employees', 'contract_start_date')) {
            Schema::table('hrm_employees', function (Blueprint $table) {
                $table->dropColumn('contract_start_date');
            });
        }
    }
};

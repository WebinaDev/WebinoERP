<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hrm_timesheets') && ! Schema::hasColumn('hrm_timesheets', 'hour_type')) {
            Schema::table('hrm_timesheets', function (Blueprint $table) {
                // standard, overtime, night, holiday
                $table->string('hour_type', 32)->default('standard')->after('hours');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('hrm_timesheets') && Schema::hasColumn('hrm_timesheets', 'hour_type')) {
            Schema::table('hrm_timesheets', function (Blueprint $table) {
                $table->dropColumn('hour_type');
            });
        }
    }
};

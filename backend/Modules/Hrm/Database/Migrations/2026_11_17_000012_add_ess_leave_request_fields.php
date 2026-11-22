<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hrm_leave_requests')) {
            Schema::table('hrm_leave_requests', function (Blueprint $table) {
                if (! Schema::hasColumn('hrm_leave_requests', 'leave_type_id')) {
                    $table->unsignedBigInteger('leave_type_id')->nullable()->after('employee_id');
                }
                if (! Schema::hasColumn('hrm_leave_requests', 'starts_at')) {
                    $table->dateTime('starts_at')->nullable()->after('start_date');
                }
                if (! Schema::hasColumn('hrm_leave_requests', 'ends_at')) {
                    $table->dateTime('ends_at')->nullable()->after('end_date');
                }
                if (! Schema::hasColumn('hrm_leave_requests', 'rejection_reason')) {
                    $table->text('rejection_reason')->nullable()->after('reason');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('hrm_leave_requests')) {
            Schema::table('hrm_leave_requests', function (Blueprint $table) {
                foreach (['leave_type_id', 'starts_at', 'ends_at', 'rejection_reason'] as $col) {
                    if (Schema::hasColumn('hrm_leave_requests', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};

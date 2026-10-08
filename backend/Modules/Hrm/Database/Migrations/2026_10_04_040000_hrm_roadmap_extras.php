<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roadmap follow-up: device user mapping, messaging chat ids, father name (SSO list), offboarding reason.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hrm_attendance_device_users')) {
            Schema::create('hrm_attendance_device_users', function (Blueprint $table) {
                $table->id();
                $table->foreignId('device_id')->nullable()->constrained('hrm_attendance_devices')->cascadeOnDelete();
                $table->string('device_user_id', 64);
                $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['device_id', 'device_user_id']);
                $table->index('device_user_id');
            });
        }

        if (Schema::hasTable('hrm_employee_profiles')) {
            Schema::table('hrm_employee_profiles', function (Blueprint $table) {
                if (! Schema::hasColumn('hrm_employee_profiles', 'father_name')) {
                    $table->string('father_name', 100)->nullable();
                }
                if (! Schema::hasColumn('hrm_employee_profiles', 'bale_chat_id')) {
                    $table->string('bale_chat_id', 64)->nullable();
                }
                if (! Schema::hasColumn('hrm_employee_profiles', 'telegram_chat_id')) {
                    $table->string('telegram_chat_id', 64)->nullable();
                }
            });
        }

        if (Schema::hasTable('hrm_offboardings') && ! Schema::hasColumn('hrm_offboardings', 'reason')) {
            Schema::table('hrm_offboardings', function (Blueprint $table) {
                $table->string('reason', 32)->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_attendance_device_users');
        if (Schema::hasTable('hrm_employee_profiles')) {
            Schema::table('hrm_employee_profiles', function (Blueprint $table) {
                foreach (['father_name', 'bale_chat_id', 'telegram_chat_id'] as $column) {
                    if (Schema::hasColumn('hrm_employee_profiles', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
        if (Schema::hasTable('hrm_offboardings') && Schema::hasColumn('hrm_offboardings', 'reason')) {
            Schema::table('hrm_offboardings', function (Blueprint $table) {
                $table->dropColumn('reason');
            });
        }
    }
};

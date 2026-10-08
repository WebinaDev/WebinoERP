<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hrm_attendance_devices')) {
            Schema::create('hrm_attendance_devices', function (Blueprint $table) {
                $table->id();
                $table->string('name', 150);
                $table->string('device_code', 64)->unique();
                $table->string('api_key_hash', 64);
                $table->string('vendor', 40)->default('zkteco');
                $table->string('location', 150)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hrm_attendance_punches')) {
            Schema::create('hrm_attendance_punches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('device_id')->nullable()->constrained('hrm_attendance_devices')->nullOnDelete();
                $table->foreignId('employee_id')->nullable()->constrained('hrm_employees')->nullOnDelete();
                $table->string('raw_code', 64)->nullable();
                $table->string('national_id', 20)->nullable();
                $table->dateTime('punched_at');
                $table->string('direction', 8)->default('in');
                $table->string('source', 20)->default('device');
                $table->string('status', 20)->default('applied');
                $table->string('conflict_note', 255)->nullable();
                $table->unsignedBigInteger('attendance_record_id')->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();
                $table->index(['employee_id', 'punched_at']);
                $table->index(['device_id', 'punched_at']);
            });
        }

        if (Schema::hasTable('hrm_attendance_records')) {
            Schema::table('hrm_attendance_records', function (Blueprint $table) {
                if (! Schema::hasColumn('hrm_attendance_records', 'source')) {
                    $table->string('source', 20)->default('manual')->after('status');
                }
                if (! Schema::hasColumn('hrm_attendance_records', 'device_id')) {
                    $table->unsignedBigInteger('device_id')->nullable()->after('source');
                }
            });
        }

        if (Schema::hasTable('hrm_employee_profiles')) {
            Schema::table('hrm_employee_profiles', function (Blueprint $table) {
                if (! Schema::hasColumn('hrm_employee_profiles', 'insurance_number')) {
                    $table->string('insurance_number', 32)->nullable()->after('national_id');
                }
            });
        }

        if (! Schema::hasTable('hrm_offboarding_templates')) {
            Schema::create('hrm_offboarding_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name', 150);
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hrm_offboarding_template_items')) {
            Schema::create('hrm_offboarding_template_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('template_id')->constrained('hrm_offboarding_templates')->cascadeOnDelete();
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->string('kind', 32)->default('checklist');
                $table->string('owner', 20)->default('hr');
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('required')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hrm_offboardings')) {
            Schema::create('hrm_offboardings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
                $table->foreignId('template_id')->nullable()->constrained('hrm_offboarding_templates')->nullOnDelete();
                $table->string('status', 20)->default('in_progress');
                $table->text('exit_interview_notes')->nullable();
                $table->date('last_day')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hrm_offboarding_tasks')) {
            Schema::create('hrm_offboarding_tasks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('offboarding_id')->constrained('hrm_offboardings')->cascadeOnDelete();
                $table->string('title', 200);
                $table->text('description')->nullable();
                $table->string('kind', 32)->default('checklist');
                $table->string('owner', 20)->default('hr');
                $table->string('status', 20)->default('pending');
                $table->text('notes')->nullable();
                $table->string('asset_label', 150)->nullable();
                $table->boolean('required')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hrm_approval_flows')) {
            Schema::create('hrm_approval_flows', function (Blueprint $table) {
                $table->id();
                $table->string('request_type', 40)->unique();
                $table->string('name', 150)->nullable();
                $table->json('steps');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hrm_workforce_budgets')) {
            Schema::create('hrm_workforce_budgets', function (Blueprint $table) {
                $table->id();
                $table->string('department', 150);
                $table->unsignedSmallInteger('year');
                $table->unsignedTinyInteger('month')->nullable();
                $table->unsignedInteger('headcount')->default(0);
                $table->decimal('cost_budget', 18, 2)->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['department', 'year', 'month']);
            });
        }

        if (! Schema::hasTable('hrm_calendar_feeds')) {
            Schema::create('hrm_calendar_feeds', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->unique()->constrained('hrm_employees')->cascadeOnDelete();
                $table->string('token', 64)->unique();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('hrm_timesheets')) {
            Schema::table('hrm_timesheets', function (Blueprint $table) {
                if (! Schema::hasColumn('hrm_timesheets', 'approval_step')) {
                    $table->unsignedTinyInteger('approval_step')->nullable();
                }
                if (! Schema::hasColumn('hrm_timesheets', 'current_role')) {
                    $table->string('current_role', 40)->nullable();
                }
                if (! Schema::hasColumn('hrm_timesheets', 'approval_log')) {
                    $table->json('approval_log')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_calendar_feeds');
        Schema::dropIfExists('hrm_workforce_budgets');
        Schema::dropIfExists('hrm_approval_flows');
        Schema::dropIfExists('hrm_offboarding_tasks');
        Schema::dropIfExists('hrm_offboardings');
        Schema::dropIfExists('hrm_offboarding_template_items');
        Schema::dropIfExists('hrm_offboarding_templates');
        Schema::dropIfExists('hrm_attendance_punches');
        Schema::dropIfExists('hrm_attendance_devices');
        if (Schema::hasTable('hrm_employee_profiles')) {
            Schema::table('hrm_employee_profiles', function (Blueprint $table) {
                foreach (['insurance_number'] as $column) {
                    if (Schema::hasColumn('hrm_employee_profiles', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
        if (Schema::hasTable('hrm_attendance_records')) {
            Schema::table('hrm_attendance_records', function (Blueprint $table) {
                foreach (['source', 'device_id'] as $column) {
                    if (Schema::hasColumn('hrm_attendance_records', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
        if (Schema::hasTable('hrm_timesheets')) {
            Schema::table('hrm_timesheets', function (Blueprint $table) {
                foreach (['approval_step', 'current_role', 'approval_log'] as $column) {
                    if (Schema::hasColumn('hrm_timesheets', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};

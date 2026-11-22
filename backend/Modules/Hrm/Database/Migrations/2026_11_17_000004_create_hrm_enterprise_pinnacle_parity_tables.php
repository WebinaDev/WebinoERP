<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. جداول ماتریس ضرایب و مبالغ (Lookup Tables for Formulas)
        if (! Schema::hasTable('hrm_formula_lookup_tables')) {
            Schema::create('hrm_formula_lookup_tables', function (Blueprint $table) {
                $table->id();
                $table->string('table_code', 40)->unique(); // e.g. RANK_BASE_COEFFICIENTS, SHIFT_RATES, EDUCATION_ALLOWANCES
                $table->string('title', 120);
                $table->string('key_type', 20)->default('string'); // string, integer, range
                $table->json('entries'); // key-value map or range map: {"1": 1.0, "2": 1.25, "3": 1.5, ...}
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        // 2. چرخه چندمرحله‌ای تأیید، ممیزی و وضعیت اجرای حقوق (Payroll Run Workflow Audit)
        if (Schema::hasTable('hrm_payroll_runs')) {
            Schema::table('hrm_payroll_runs', function (Blueprint $table) {
                if (! Schema::hasColumn('hrm_payroll_runs', 'workflow_state')) {
                    $table->string('workflow_state', 24)->default('draft'); // draft, calculating, review, approved, paid, locked
                }
                if (! Schema::hasColumn('hrm_payroll_runs', 'reviewed_by')) {
                    $table->unsignedBigInteger('reviewed_by')->nullable();
                }
                if (! Schema::hasColumn('hrm_payroll_runs', 'reviewed_at')) {
                    $table->timestamp('reviewed_at')->nullable();
                }
                if (! Schema::hasColumn('hrm_payroll_runs', 'locked_by')) {
                    $table->unsignedBigInteger('locked_by')->nullable();
                }
                if (! Schema::hasColumn('hrm_payroll_runs', 'locked_at')) {
                    $table->timestamp('locked_at')->nullable();
                }
            });
        }

        // 3. درخواست‌های اصلاح تردد و مجوزهای ثبت دسترسی تردد (Attendance Punch Correction Requests)
        if (! Schema::hasTable('hrm_attendance_correction_requests')) {
            Schema::create('hrm_attendance_correction_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
                $table->date('record_date');
                $table->time('requested_clock_in')->nullable();
                $table->time('requested_clock_out')->nullable();
                $table->string('reason_type', 32)->default('device_failure'); // device_failure, forgot_card, external_meeting, mission
                $table->text('description')->nullable();
                $table->string('status', 24)->default('pending'); // pending, approved_by_manager, approved_by_hr, rejected
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamps();
            });
        }

        // 4. تسویه حساب نهایی پرسنل (Final Settlement: Eydi, Severance, Unused Leave Buyback)
        if (! Schema::hasTable('hrm_employee_settlements')) {
            Schema::create('hrm_employee_settlements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
                $table->date('settlement_date');
                $table->string('reason', 32)->default('resignation'); // resignation, termination, end_of_contract, retirement
                $table->decimal('tenure_years', 5, 2)->default(0);
                $table->decimal('severance_amount', 18, 2)->default(0); // بازخرید سنوات خدمت (ماده ۲۴)
                $table->decimal('unused_leave_days', 5, 2)->default(0);
                $table->decimal('leave_buyback_amount', 18, 2)->default(0); // بازخرید مرخصی استفاده‌نشده
                $table->decimal('pro_rated_eydi_amount', 18, 2)->default(0); // عیدی و پاداش معوق تا روز ترک کار
                $table->decimal('final_net_payable', 18, 2)->default(0); // خالص پرداختی تسویه
                $table->string('status', 24)->default('draft'); // draft, verified, settled
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_employee_settlements');
        Schema::dropIfExists('hrm_attendance_correction_requests');
        Schema::dropIfExists('hrm_formula_lookup_tables');
        if (Schema::hasTable('hrm_payroll_runs')) {
            Schema::table('hrm_payroll_runs', function (Blueprint $table) {
                $table->dropColumn(['workflow_state', 'reviewed_by', 'reviewed_at', 'locked_by', 'locked_at']);
            });
        }
    }
};

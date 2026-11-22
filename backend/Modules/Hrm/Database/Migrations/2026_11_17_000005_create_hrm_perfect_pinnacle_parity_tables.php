<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. ثبت شرکت‌های هلدینگ و کارگاه‌های تفکیکی (Holding Companies & Multiple SSO Workshops)
        if (! Schema::hasTable('hrm_workshops')) {
            Schema::create('hrm_workshops', function (Blueprint $table) {
                $table->id();
                $table->string('company_code', 32)->default('MAIN');
                $table->string('company_name', 120);
                $table->string('workshop_code', 10); // ۱۰ رقم عددی معتبر تأمین اجتماعی
                $table->string('workshop_name', 120);
                $table->string('employer_name', 120);
                $table->string('tax_tfn', 20)->nullable(); // شناسه ملی / کد اقتصادی کارفرما
                $table->string('postal_code', 10)->nullable(); // کد پستی ۱۰ رقمی کارگاه
                $table->string('address', 255)->nullable();
                $table->string('sso_contract_row', 3)->default('000');
                $table->decimal('employer_rate', 5, 2)->default(20.00);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 2. تقویم داینامیک تعطیلات رسمی سازمان و کشور (Dynamic Statutory Calendar)
        if (! Schema::hasTable('hrm_calendar_holidays')) {
            Schema::create('hrm_calendar_holidays', function (Blueprint $table) {
                $table->id();
                $table->date('holiday_date')->unique();
                $table->string('title', 120);
                $table->string('holiday_type', 24)->default('statutory'); // statutory, company_specific, emergency
                $table->boolean('is_recurring_yearly')->default(false);
                $table->timestamps();
            });
        }

        // 3. تاریخچه و لاگ ممیزی کامل تغییر وضعیت حقوق (Payroll Run Audit Logs)
        if (! Schema::hasTable('hrm_payroll_run_audit_logs')) {
            Schema::create('hrm_payroll_run_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payroll_run_id')->constrained('hrm_payroll_runs')->cascadeOnDelete();
                $table->string('from_state', 24);
                $table->string('to_state', 24);
                $table->unsignedBigInteger('performed_by')->nullable();
                $table->text('action_note')->nullable();
                $table->json('state_snapshot')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_payroll_run_audit_logs');
        Schema::dropIfExists('hrm_calendar_holidays');
        Schema::dropIfExists('hrm_workshops');
    }
};

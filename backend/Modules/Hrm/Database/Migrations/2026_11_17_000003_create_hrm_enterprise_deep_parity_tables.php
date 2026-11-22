<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. بسته‌های پیشرفته جبران خدمات به تفکیک گروه استخدامی (Compensation Packages)
        if (! Schema::hasTable('hrm_compensation_packages')) {
            Schema::create('hrm_compensation_packages', function (Blueprint $table) {
                $table->id();
                $table->string('code', 32)->unique();
                $table->string('title', 120);
                $table->string('employment_group', 64)->default('standard_staff'); // standard_staff, executive, oil_and_gas_special, contractor, shift_worker
                $table->decimal('base_ratio', 5, 2)->default(1.00);
                $table->json('included_component_codes')->nullable(); // list of formula component codes
                $table->json('industry_rules')->nullable(); // special industry scenario configs (e.g. oil shift allowances, remote site hazard)
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 2. موافقت‌نامه رسمی عملکرد کارکنان (Performance Agreements)
        if (! Schema::hasTable('hrm_performance_agreements')) {
            Schema::create('hrm_performance_agreements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
                $table->foreignId('manager_id')->nullable()->constrained('hrm_employees')->nullOnDelete();
                $table->foreignId('cycle_id')->nullable()->constrained('hrm_performance_cycles')->nullOnDelete();
                $table->string('agreement_code', 64)->unique();
                $table->date('start_date');
                $table->date('end_date');
                $table->json('kpi_targets')->nullable(); // quantitative, dynamic & calculation weights
                $table->json('competency_targets')->nullable(); // behavioral & leadership competencies
                $table->decimal('midterm_progress_score', 5, 2)->default(0.00);
                $table->decimal('final_score', 5, 2)->default(0.00);
                $table->string('status', 32)->default('draft'); // draft, submitted, approved_by_manager, active, completed
                $table->timestamp('manager_signed_at')->nullable();
                $table->timestamp('employee_signed_at')->nullable();
                $table->timestamps();
            });
        }

        // 3. ثبت احکام و هزینه‌های مأموریت اداری (Missions & Travel Orders)
        if (! Schema::hasTable('hrm_mission_orders')) {
            Schema::create('hrm_mission_orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
                $table->string('mission_no', 64)->unique();
                $table->string('mission_type', 24)->default('daily'); // hourly, daily, domestic, overseas
                $table->string('destination', 150);
                $table->dateTime('starts_at');
                $table->dateTime('ends_at');
                $table->decimal('per_diem_rate', 15, 2)->default(0); // حق مأموریت روزانه/ساعتی
                $table->decimal('transport_expense', 15, 2)->default(0); // هزینه ایاب و ذهاب
                $table->decimal('hotel_expense', 15, 2)->default(0); // هزینه اقامت
                $table->decimal('total_allowance', 15, 2)->default(0); // جمع کل پرداختی مأموریت
                $table->string('status', 24)->default('draft'); // draft, manager_approved, hr_approved, settled
                $table->text('mission_report')->nullable();
                $table->timestamps();
            });
        }

        // 4. اسناد حسابداری حقوق و دستمزد (Payroll Accounting Voucher Mapping)
        if (! Schema::hasTable('hrm_payroll_journal_links')) {
            Schema::create('hrm_payroll_journal_links', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payroll_run_id')->constrained('hrm_payroll_runs')->cascadeOnDelete();
                $table->unsignedBigInteger('journal_entry_id')->nullable(); // references acc_journal_entries
                $table->string('voucher_segment', 32); // current_run, retro_current_year, retro_prior_years, sso_payable, tax_payable
                $table->decimal('total_debit', 18, 2)->default(0);
                $table->decimal('total_credit', 18, 2)->default(0);
                $table->string('status', 24)->default('posted');
                $table->json('lines_detail')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_payroll_journal_links');
        Schema::dropIfExists('hrm_mission_orders');
        Schema::dropIfExists('hrm_performance_agreements');
        Schema::dropIfExists('hrm_compensation_packages');
    }
};

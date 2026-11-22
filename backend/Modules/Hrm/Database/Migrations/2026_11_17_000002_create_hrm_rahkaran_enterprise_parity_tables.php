<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. عوامل حکمی و فرمول‌نویسی پیشرفته (Formulaic Decree Salary Components)
        if (! Schema::hasTable('hrm_decree_formula_components')) {
            Schema::create('hrm_decree_formula_components', function (Blueprint $table) {
                $table->id();
                $table->string('code', 32)->unique(); // e.g. BASE_SALARY, SENIORITY_BONUS, JOB_DIFFICULTY
                $table->string('title', 120);
                $table->string('component_type', 24)->default('earning'); // earning, deduction, benefit
                $table->string('calculation_type', 24)->default('formula'); // fixed, formula, table_lookup
                $table->text('formula_expression')->nullable(); // e.g. "BASE * 0.15 + (YEARS_EXP * 2500000)"
                $table->boolean('is_taxable')->default(true);
                $table->boolean('is_insurable')->default(true);
                $table->boolean('is_active')->default(true);
                $table->json('applicable_ranks')->nullable(); // applicable job grades/ranks
                $table->timestamps();
            });
        }

        // 2. مسیر شغلی، رده‌بندی و ارتقا (Career Paths & Automated Promotion Engine)
        if (! Schema::hasTable('hrm_career_paths')) {
            Schema::create('hrm_career_paths', function (Blueprint $table) {
                $table->id();
                $table->string('job_family', 80); // e.g. مهندسی نرم‌افزار, مالی و اداری
                $table->integer('level'); // 1, 2, 3, 4, 5, ...
                $table->string('title_fa', 120); // Junior, Mid, Senior, Lead, Principal
                $table->integer('min_tenure_months')->default(12);
                $table->decimal('min_performance_score', 5, 2)->default(80.00); // minimum performance threshold
                $table->json('required_skills')->nullable();
                $table->json('required_certifications')->nullable();
                $table->decimal('base_salary_multiplier', 5, 2)->default(1.00);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hrm_promotion_proposals')) {
            Schema::create('hrm_promotion_proposals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
                $table->foreignId('current_career_path_id')->nullable()->constrained('hrm_career_paths')->nullOnDelete();
                $table->foreignId('target_career_path_id')->constrained('hrm_career_paths')->cascadeOnDelete();
                $table->string('proposal_source', 32)->default('system_automated'); // system_automated, manager_initiated
                $table->decimal('eligibility_score', 5, 2)->default(0.00);
                $table->text('system_justification')->nullable();
                $table->string('status', 24)->default('pending_review'); // pending_review, approved, decree_issued, rejected
                $table->foreignId('decree_id')->nullable()->constrained('hrm_employment_decrees')->nullOnDelete();
                $table->timestamps();
            });
        }

        // 3. محاسبه و خروجی دیسکت قانونی بیمه تأمین اجتماعی و مالیات حقوق (SSO & Tax Diskette Generator)
        if (! Schema::hasTable('hrm_legal_export_records')) {
            Schema::create('hrm_legal_export_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payroll_run_id')->constrained('hrm_payroll_runs')->cascadeOnDelete();
                $table->string('export_type', 32); // sso_diskette, tax_diskette, bank_payment
                $table->string('format', 16)->default('txt'); // txt, csv, dat
                $table->string('file_path', 255)->nullable();
                $table->integer('total_records')->default(0);
                $table->decimal('total_amount', 18, 2)->default(0);
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        // 4. امور بازنشستگان و پس‌انداز سنوات (Retirees & Severance Fund Management)
        if (! Schema::hasTable('hrm_retiree_records')) {
            Schema::create('hrm_retiree_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
                $table->date('retirement_date');
                $table->string('pension_fund_type', 32)->default('sso'); // sso, civil_service, armed_forces
                $table->decimal('final_severance_amount', 18, 2)->default(0); // مبلغ کل تسویه سنوات
                $table->decimal('unused_leave_buyback_amount', 18, 2)->default(0); // بازخرید مانده مرخصی
                $table->decimal('pension_supplement_monthly', 18, 2)->default(0); // حقوق تکمیلی بازنشستگی
                $table->string('status', 24)->default('active'); // active, suspended, terminated
                $table->json('survivors_data')->nullable(); // اطلاعات وراث تحت تکفل
                $table->timestamps();
            });
        }

        // 5. ارزیابی اثربخشی آموزش (Training Effectiveness & Kirkpatrick Model)
        if (! Schema::hasTable('hrm_training_effectiveness_evaluations')) {
            Schema::create('hrm_training_effectiveness_evaluations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('enrollment_id')->constrained('hrm_training_enrollments')->cascadeOnDelete();
                $table->integer('kirkpatrick_level')->default(1); // 1: Reaction, 2: Learning, 3: Behavior, 4: Results
                $table->decimal('score', 5, 2)->default(0); // 0.00 - 100.00
                $table->text('manager_feedback')->nullable();
                $table->text('kpi_impact_summary')->nullable();
                $table->date('evaluation_date');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_training_effectiveness_evaluations');
        Schema::dropIfExists('hrm_retiree_records');
        Schema::dropIfExists('hrm_legal_export_records');
        Schema::dropIfExists('hrm_promotion_proposals');
        Schema::dropIfExists('hrm_career_paths');
        Schema::dropIfExists('hrm_decree_formula_components');
    }
};

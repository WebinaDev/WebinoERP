<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Retroactive Payroll Adjustment Runs
        if (! Schema::hasTable('hrm_payroll_retro_adjustments')) {
            Schema::create('hrm_payroll_retro_adjustments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
                $table->foreignId('decree_id')->nullable()->constrained('hrm_employment_decrees')->nullOnDelete();
                $table->foreignId('payroll_run_id')->nullable()->constrained('hrm_payroll_runs')->nullOnDelete();
                $table->integer('target_year');
                $table->integer('target_month');
                $table->decimal('original_gross', 15, 2)->default(0);
                $table->decimal('adjusted_gross', 15, 2)->default(0);
                $table->decimal('differential_amount', 15, 2)->default(0);
                $table->decimal('differential_insurance', 15, 2)->default(0);
                $table->decimal('differential_tax', 15, 2)->default(0);
                $table->decimal('differential_net', 15, 2)->default(0);
                $table->string('status', 24)->default('pending'); // pending, applied, paid
                $table->text('reason')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        // 2. Automated Recruitment AI Resume Match & Candidate Scoring
        if (! Schema::hasTable('hrm_resume_ai_matches')) {
            Schema::create('hrm_resume_ai_matches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('applicant_id')->constrained('hrm_job_applicants')->cascadeOnDelete();
                $table->foreignId('posting_id')->constrained('hrm_job_postings')->cascadeOnDelete();
                $table->decimal('overall_score', 5, 2)->default(0); // 0.00 - 100.00
                $table->decimal('skills_match_score', 5, 2)->default(0);
                $table->decimal('experience_match_score', 5, 2)->default(0);
                $table->decimal('education_match_score', 5, 2)->default(0);
                $table->json('matched_skills')->nullable();
                $table->json('missing_skills')->nullable();
                $table->text('ai_summary_fa')->nullable();
                $table->string('recommendation', 32)->default('review'); // highly_recommended, recommended, review, rejected
                $table->timestamps();
            });
        }

        // 3. Employee Attrition Risk & Retention Analytics (Predictive AI)
        if (! Schema::hasTable('hrm_attrition_risk_logs')) {
            Schema::create('hrm_attrition_risk_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
                $table->string('risk_level', 20)->default('low'); // low, medium, high, critical
                $table->decimal('risk_probability', 5, 2)->default(0); // 0 - 100%
                $table->json('top_contributing_factors')->nullable(); // e.g. ["overtime_exhaustion", "low_salary_ratio", "no_promotion_2yrs"]
                $table->text('recommended_actions')->nullable();
                $table->date('calculated_date');
                $table->timestamps();
                $table->index(['employee_id', 'calculated_date']);
            });
        }

        // 4. Enhance HrmEmploymentDecree with effective dating & retro pay flag
        if (Schema::hasTable('hrm_employment_decrees')) {
            Schema::table('hrm_employment_decrees', function (Blueprint $table) {
                if (! Schema::hasColumn('hrm_employment_decrees', 'is_retroactive')) {
                    $table->boolean('is_retroactive')->default(false);
                }
                if (! Schema::hasColumn('hrm_employment_decrees', 'retro_applied_at')) {
                    $table->timestamp('retro_applied_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_attrition_risk_logs');
        Schema::dropIfExists('hrm_resume_ai_matches');
        Schema::dropIfExists('hrm_payroll_retro_adjustments');
        if (Schema::hasTable('hrm_employment_decrees')) {
            Schema::table('hrm_employment_decrees', function (Blueprint $table) {
                if (Schema::hasColumn('hrm_employment_decrees', 'is_retroactive')) {
                    $table->dropColumn('is_retroactive');
                }
                if (Schema::hasColumn('hrm_employment_decrees', 'retro_applied_at')) {
                    $table->dropColumn('retro_applied_at');
                }
            });
        }
    }
};

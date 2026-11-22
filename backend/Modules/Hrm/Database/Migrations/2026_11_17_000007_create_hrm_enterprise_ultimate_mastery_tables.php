<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Employee subsidiary company binding
        if (Schema::hasTable('hrm_employees') && ! Schema::hasColumn('hrm_employees', 'subsidiary_company_id')) {
            Schema::table('hrm_employees', function (Blueprint $table) {
                $table->foreignId('subsidiary_company_id')->nullable()->constrained('hrm_subsidiary_companies')->nullOnDelete();
            });
        }

        // 2. Performance Appraisal Agreements (موافقت‌نامه‌های عملکرد و ارزیابی شایستگی)
        if (! Schema::hasTable('hrm_performance_agreements')) {
            Schema::create('hrm_performance_agreements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
                $table->foreignId('cycle_id')->nullable()->constrained('hrm_performance_cycles')->nullOnDelete();
                $table->string('year_period', 20); // e.g. 1405-H1
                $table->string('stage', 32)->default('planning'); // planning, monitoring, evaluation, finalized
                $table->decimal('final_score', 5, 2)->nullable();
                $table->decimal('performance_bonus_suggested', 15, 2)->default(0);
                $table->text('manager_notes')->nullable();
                $table->timestamps();
            });
        }

        // 3. Compensation packages by employment grade/post (بسته‌های جبران خدمات شغلی)
        if (! Schema::hasTable('hrm_compensation_packages')) {
            Schema::create('hrm_compensation_packages', function (Blueprint $table) {
                $table->id();
                $table->string('code', 32)->unique();
                $table->string('title', 120);
                $table->string('job_grade', 32)->nullable();
                $table->decimal('base_salary_floor', 15, 2)->default(0);
                $table->json('included_components')->nullable(); // component codes and fixed/percentage rules
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_compensation_packages');
        Schema::dropIfExists('hrm_performance_agreements');
        if (Schema::hasTable('hrm_employees') && Schema::hasColumn('hrm_employees', 'subsidiary_company_id')) {
            Schema::table('hrm_employees', function (Blueprint $table) {
                $table->dropConstrainedForeignId('subsidiary_company_id');
            });
        }
    }
};

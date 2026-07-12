<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hrm_employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->unique()->constrained('hrm_employees')->cascadeOnDelete();
            $table->string('national_id', 20)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender', 10)->nullable();
            $table->text('address')->nullable();
            $table->string('emergency_contact', 100)->nullable();
            $table->string('emergency_phone', 20)->nullable();
            $table->json('custom_fields')->nullable();
            $table->timestamps();
        });

        Schema::create('hrm_org_positions', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->string('department', 100)->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('hrm_org_positions')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('hrm_leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->unsignedSmallInteger('default_days')->default(0);
            $table->boolean('is_paid')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('hrm_leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('hrm_leave_types')->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('allocated', 8, 2)->default(0);
            $table->decimal('used', 8, 2)->default(0);
            $table->timestamps();
            $table->unique(['employee_id', 'leave_type_id', 'year']);
        });

        Schema::create('hrm_payroll_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('hrm_payroll_components', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('type', 20); // earning|deduction
            $table->string('calculation', 30)->default('fixed');
            $table->decimal('default_amount', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('hrm_employee_salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
            $table->decimal('base_salary', 15, 2)->default(0);
            $table->json('components')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();
        });

        Schema::create('hrm_job_applicants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_posting_id')->constrained('hrm_job_postings')->cascadeOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 150)->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('status', 20)->default('applied');
            $table->text('resume_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('hrm_interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained('hrm_job_applicants')->cascadeOnDelete();
            $table->dateTime('scheduled_at');
            $table->string('interviewer', 150)->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('hrm_kpi_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->json('criteria')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('hrm_performance_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('open');
            $table->timestamps();
        });

        Schema::create('hrm_training_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('hrm_training_courses')->cascadeOnDelete();
            $table->string('title', 200);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->string('location', 200)->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_training_sessions');
        Schema::dropIfExists('hrm_performance_cycles');
        Schema::dropIfExists('hrm_kpi_templates');
        Schema::dropIfExists('hrm_interviews');
        Schema::dropIfExists('hrm_job_applicants');
        Schema::dropIfExists('hrm_employee_salaries');
        Schema::dropIfExists('hrm_payroll_components');
        Schema::dropIfExists('hrm_payroll_settings');
        Schema::dropIfExists('hrm_leave_balances');
        Schema::dropIfExists('hrm_leave_types');
        Schema::dropIfExists('hrm_org_positions');
        Schema::dropIfExists('hrm_employee_profiles');
    }
};

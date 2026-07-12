<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hrm_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('employee_code', 50)->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 150)->nullable();
            $table->string('mobile', 20)->nullable();
            $table->string('department', 100)->nullable();
            $table->string('position', 100)->nullable();
            $table->date('hire_date')->nullable();
            $table->string('status', 20)->default('active');
            $table->decimal('base_salary', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('hrm_attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
            $table->date('date');
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->string('status', 20)->default('present');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'date']);
        });

        Schema::create('hrm_leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
            $table->string('type', 30);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 20)->default('pending');
            $table->text('reason')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('hrm_payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('status', 20)->default('draft');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('hrm_payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('hrm_payroll_runs')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
            $table->decimal('gross', 15, 2)->default(0);
            $table->decimal('deductions', 15, 2)->default(0);
            $table->decimal('net', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('hrm_job_postings', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('department', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('open');
            $table->date('closes_at')->nullable();
            $table->timestamps();
        });

        Schema::create('hrm_performance_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
            $table->string('period', 50);
            $table->unsignedTinyInteger('score')->nullable();
            $table->text('feedback')->nullable();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('hrm_training_courses', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default('scheduled');
            $table->timestamps();
        });

        Schema::create('hrm_training_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('hrm_training_courses')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
            $table->string('status', 20)->default('enrolled');
            $table->timestamps();
            $table->unique(['course_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_training_enrollments');
        Schema::dropIfExists('hrm_training_courses');
        Schema::dropIfExists('hrm_performance_reviews');
        Schema::dropIfExists('hrm_job_postings');
        Schema::dropIfExists('hrm_payroll_items');
        Schema::dropIfExists('hrm_payroll_runs');
        Schema::dropIfExists('hrm_leave_requests');
        Schema::dropIfExists('hrm_attendance_records');
        Schema::dropIfExists('hrm_employees');
    }
};

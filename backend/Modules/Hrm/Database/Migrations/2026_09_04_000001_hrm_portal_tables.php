<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hrm_shift_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->unsignedSmallInteger('grace_minutes')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('hrm_employees', function (Blueprint $table) {
            $table->foreignId('shift_template_id')->nullable()->after('notes')
                ->constrained('hrm_shift_templates')->nullOnDelete();
        });

        Schema::create('hrm_notices', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->text('body')->nullable();
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('hrm_dependents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
            $table->string('full_name', 150);
            $table->string('relation', 50)->nullable();
            $table->string('national_id', 20)->nullable();
            $table->date('birth_date')->nullable();
            $table->timestamps();
        });

        Schema::create('hrm_employment_decrees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->nullable()->constrained('hrm_employees')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decree_no', 50)->nullable();
            $table->string('decree_type', 40)->default('hire');
            $table->string('status', 30)->default('draft');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->string('job_title', 150)->nullable();
            $table->string('department', 100)->nullable();
            $table->string('contract_type', 50)->nullable();
            $table->string('job_code', 50)->nullable();
            $table->decimal('base_salary', 15, 2)->default(0);
            $table->decimal('daily_wage', 15, 2)->default(0);
            $table->unsignedBigInteger('workshop_id')->nullable();
            $table->timestamps();
        });

        Schema::create('hrm_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->nullable()->constrained('hrm_employees')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 50);
            $table->string('status', 30)->default('pending_hr');
            $table->json('payload')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_requests');
        Schema::dropIfExists('hrm_employment_decrees');
        Schema::dropIfExists('hrm_dependents');
        Schema::dropIfExists('hrm_notices');

        Schema::table('hrm_employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_template_id');
        });

        Schema::dropIfExists('hrm_shift_templates');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. تاریخچه تغییرات ساختار سازمانی و پست‌های مصوب / غیرمصوب
        if (! Schema::hasTable('hrm_org_positions')) {
            Schema::create('hrm_org_positions', function (Blueprint $table) {
                $table->id();
                $table->string('code', 32)->unique();
                $table->string('title', 120);
                $table->foreignId('department_id')->nullable()->constrained('hrm_departments')->nullOnDelete();
                $table->foreignId('parent_position_id')->nullable()->constrained('hrm_org_positions')->nullOnDelete();
                $table->boolean('is_approved_quota')->default(true); // پست مصوب یا غیرمصوب
                $table->integer('job_grade')->default(1); // پایه یا رتبه مصوب طرح طبقه‌بندی
                $table->string('status', 24)->default('vacant'); // vacant, occupied, frozen
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hrm_org_structure_histories')) {
            Schema::create('hrm_org_structure_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('position_id')->constrained('hrm_org_positions')->cascadeOnDelete();
                $table->foreignId('employee_id')->nullable()->constrained('hrm_employees')->nullOnDelete();
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->string('change_type', 32); // appointment, promotion, transfer, demotion, vacancy
                $table->string('decree_no', 64)->nullable();
                $table->text('change_reason')->nullable();
                $table->timestamps();
            });
        }

        // 2. صدور احکام گروهی کارگزینی با گردش کار رسمی (Bulk Employment Decrees)
        if (! Schema::hasTable('hrm_bulk_decree_batches')) {
            Schema::create('hrm_bulk_decree_batches', function (Blueprint $table) {
                $table->id();
                $table->string('batch_no', 64)->unique();
                $table->string('title', 150);
                $table->string('decree_type', 64); // annual_increase, minimum_wage_adjustment, grade_promotion
                $table->date('effective_from');
                $table->decimal('percentage_increase', 5, 2)->default(0);
                $table->decimal('fixed_amount_increase', 15, 2)->default(0);
                $table->string('status', 24)->default('draft'); // draft, processed, applied
                $table->integer('total_employees')->default(0);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_bulk_decree_batches');
        Schema::dropIfExists('hrm_org_structure_histories');
        Schema::dropIfExists('hrm_org_positions');
    }
};

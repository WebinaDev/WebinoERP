<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. سازنده گزارش‌های پیشرفته و سفارشی منابع انسانی (Dynamic HR Report Builder)
        if (! Schema::hasTable('hrm_custom_report_definitions')) {
            Schema::create('hrm_custom_report_definitions', function (Blueprint $table) {
                $table->id();
                $table->string('code', 64)->unique();
                $table->string('title_fa', 150);
                $table->string('title_en', 150)->nullable();
                $table->string('category', 64)->default('payroll'); // payroll, attendance, workforce, compliance
                $table->json('selected_columns'); // ['employee_name', 'national_id', 'base_salary', 'net_pay', 'department']
                $table->json('filter_criteria')->nullable(); // conditions (department, rank, salary range)
                $table->json('sorting_rules')->nullable();
                $table->boolean('is_system_preset')->default(false);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        // 2. امنیت داده در سطح رکورد و سرفصل (Enterprise HR Field & Row Security Audit)
        if (! Schema::hasTable('hrm_data_access_audit_logs')) {
            Schema::create('hrm_data_access_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('entity_type', 80); // HrmEmployee, HrmPayrollItem, HrmEmploymentDecree
                $table->unsignedBigInteger('entity_id');
                $table->string('access_type', 32); // read_confidential, salary_export, bulk_view
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->json('accessed_fields')->nullable(); // ['base_salary', 'sheba_number', 'national_id']
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_data_access_audit_logs');
        Schema::dropIfExists('hrm_custom_report_definitions');
    }
};

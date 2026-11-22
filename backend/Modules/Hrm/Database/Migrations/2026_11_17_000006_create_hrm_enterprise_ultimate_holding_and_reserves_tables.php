<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. شرکت‌های زیرمجموعه هلدینگ با تنظیمات مالی، بانکی و بیمه مستقل
        if (! Schema::hasTable('hrm_subsidiary_companies')) {
            Schema::create('hrm_subsidiary_companies', function (Blueprint $table) {
                $table->id();
                $table->string('code', 32)->unique(); // e.g. PETRO_CO, STEEL_CO, TECH_CO
                $table->string('name', 120);
                $table->string('tax_tfn', 20); // شناسه ملی / کد اقتصادی ۱۱ رقمی شرکت
                $table->string('postal_code', 10);
                $table->string('company_sheba', 26); // شبای مبدأ پرداخت شرکت
                $table->string('workshop_code', 10); // کد کارگاه ۱۰ رقمی تأمین اجتماعی
                $table->string('employer_name', 120);
                $table->string('payroll_policy_model', 32)->default('semi_centralized'); // centralized, semi_centralized, independent
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 2. پیوند دوره حقوق به شرکت مشخص هلدینگ
        if (Schema::hasTable('hrm_payroll_runs') && ! Schema::hasColumn('hrm_payroll_runs', 'subsidiary_company_id')) {
            Schema::table('hrm_payroll_runs', function (Blueprint $table) {
                $table->foreignId('subsidiary_company_id')->nullable()->constrained('hrm_subsidiary_companies')->nullOnDelete();
            });
        }

        // 3. جدول تجمیعی و دوره‌ای ذخیره سنوات خدمت پرسنل (Accrued Severance Reserves)
        if (! Schema::hasTable('hrm_severance_reserves')) {
            Schema::create('hrm_severance_reserves', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
                $table->foreignId('payroll_run_id')->nullable()->constrained('hrm_payroll_runs')->nullOnDelete();
                $table->integer('year');
                $table->integer('month');
                $table->decimal('monthly_provision', 15, 2); // یک‌دوازدهم حقوق پایه دوره
                $table->decimal('cumulative_balance', 18, 2); // مانده کل سنوات تا این دوره
                $table->string('status', 24)->default('accrued'); // accrued, paid, settled
                $table->timestamps();
                $table->unique(['employee_id', 'year', 'month']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_severance_reserves');
        if (Schema::hasTable('hrm_payroll_runs') && Schema::hasColumn('hrm_payroll_runs', 'subsidiary_company_id')) {
            Schema::table('hrm_payroll_runs', function (Blueprint $table) {
                $table->dropConstrainedForeignId('subsidiary_company_id');
            });
        }
        Schema::dropIfExists('hrm_subsidiary_companies');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Iranian ESS gaps: loans/advances, personnel files, payslip & decree templates,
 * contract fields, insurance flags on dependents, payroll breakdown, targeted notices.
 * Statutory numbers are editable defaults (budget 1405 ladder in Rials, SSO 7/20/3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hrm_employees', function (Blueprint $table) {
            if (! Schema::hasColumn('hrm_employees', 'contract_type')) {
                $table->string('contract_type', 40)->nullable()->after('hire_date');
            }
            if (! Schema::hasColumn('hrm_employees', 'contract_end_date')) {
                $table->date('contract_end_date')->nullable()->after('contract_type');
            }
            if (! Schema::hasColumn('hrm_employees', 'contract_status')) {
                $table->string('contract_status', 30)->default('active')->after('contract_end_date');
            }
        });

        Schema::table('hrm_dependents', function (Blueprint $table) {
            if (! Schema::hasColumn('hrm_dependents', 'is_insured')) {
                $table->boolean('is_insured')->default(true)->after('birth_date');
            }
            if (! Schema::hasColumn('hrm_dependents', 'coverage_start')) {
                $table->date('coverage_start')->nullable()->after('is_insured');
            }
        });

        Schema::table('hrm_payroll_components', function (Blueprint $table) {
            if (! Schema::hasColumn('hrm_payroll_components', 'code')) {
                $table->string('code', 40)->nullable()->unique()->after('name');
            }
            if (! Schema::hasColumn('hrm_payroll_components', 'is_insurable')) {
                $table->boolean('is_insurable')->default(false)->after('is_active');
            }
            if (! Schema::hasColumn('hrm_payroll_components', 'is_taxable')) {
                $table->boolean('is_taxable')->default(true)->after('is_insurable');
            }
        });

        Schema::table('hrm_payroll_items', function (Blueprint $table) {
            if (! Schema::hasColumn('hrm_payroll_items', 'breakdown')) {
                $table->json('breakdown')->nullable()->after('net');
            }
        });

        Schema::table('hrm_notices', function (Blueprint $table) {
            if (! Schema::hasColumn('hrm_notices', 'employee_id')) {
                $table->foreignId('employee_id')->nullable()->after('id')
                    ->constrained('hrm_employees')->cascadeOnDelete();
            }
            if (! Schema::hasColumn('hrm_notices', 'kind')) {
                $table->string('kind', 40)->default('general')->after('body');
            }
        });

        if (! Schema::hasTable('hrm_loans')) {
            Schema::create('hrm_loans', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
                $table->string('type', 20)->default('loan'); // loan | advance
                $table->string('title', 200)->nullable();
                $table->decimal('principal', 15, 2);
                $table->decimal('installment_amount', 15, 2);
                $table->decimal('remaining_balance', 15, 2);
                $table->unsignedSmallInteger('installments_total')->default(0);
                $table->unsignedSmallInteger('installments_paid')->default(0);
                $table->date('start_date')->nullable();
                $table->string('status', 20)->default('active'); // active|settled|cancelled
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hrm_loan_installments')) {
            Schema::create('hrm_loan_installments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('loan_id')->constrained('hrm_loans')->cascadeOnDelete();
                $table->foreignId('payroll_run_id')->nullable()->constrained('hrm_payroll_runs')->nullOnDelete();
                $table->decimal('amount', 15, 2);
                $table->date('paid_on')->nullable();
                $table->string('status', 20)->default('paid');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hrm_personnel_documents')) {
            Schema::create('hrm_personnel_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
                $table->string('title', 200);
                $table->string('category', 40)->default('other');
                $table->string('file_path', 255);
                $table->string('original_name', 255);
                $table->string('mime', 120)->nullable();
                $table->unsignedInteger('size')->default(0);
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->date('expires_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('hrm_document_templates')) {
            Schema::create('hrm_document_templates', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 40)->unique();
                $table->string('name', 150);
                $table->longText('html');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        $this->seedDefaults();
    }

    private function seedDefaults(): void
    {
        $settings = [
            'employee_insurance_percent' => 7,
            'employer_insurance_percent' => 20,
            'unemployment_insurance_percent' => 3,
            'insurance_ceiling' => 0,
            'overtime_multiplier' => 1.4,
            'monthly_hours' => 220,
            'mission_daily_allowance' => 0,
            'minimum_monthly_wage' => 0,
            'eydi_month' => 12,
            'insurance_deductible_fraction' => 0.285714,
            'tax_brackets' => [
                ['up_to' => 400000000, 'rate' => 0],
                ['up_to' => 800000000, 'rate' => 10],
                ['up_to' => 1000000000, 'rate' => 15],
                ['up_to' => 1200000000, 'rate' => 20],
                ['up_to' => 1400000000, 'rate' => 25],
                ['up_to' => null, 'rate' => 30],
            ],
        ];

        foreach ($settings as $key => $value) {
            $exists = DB::table('hrm_payroll_settings')->where('key', $key)->exists();
            if (! $exists) {
                DB::table('hrm_payroll_settings')->insert([
                    'key' => $key,
                    'value' => json_encode($value, JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $components = [
            ['code' => 'bon', 'name' => 'بن خواربار', 'type' => 'earning', 'calculation' => 'fixed', 'default_amount' => 0, 'is_insurable' => true, 'is_taxable' => false],
            ['code' => 'housing', 'name' => 'حق مسکن', 'type' => 'earning', 'calculation' => 'fixed', 'default_amount' => 0, 'is_insurable' => true, 'is_taxable' => false],
            ['code' => 'child', 'name' => 'حق اولاد', 'type' => 'earning', 'calculation' => 'per_insured_dependent', 'default_amount' => 0, 'is_insurable' => false, 'is_taxable' => false],
            ['code' => 'seniority', 'name' => 'پایه سنوات', 'type' => 'earning', 'calculation' => 'fixed', 'default_amount' => 0, 'is_insurable' => true, 'is_taxable' => true],
            ['code' => 'reward', 'name' => 'پاداش', 'type' => 'earning', 'calculation' => 'fixed', 'default_amount' => 0, 'is_insurable' => false, 'is_taxable' => true],
            ['code' => 'eydi', 'name' => 'عیدی', 'type' => 'earning', 'calculation' => 'eydi', 'default_amount' => 0, 'is_insurable' => false, 'is_taxable' => false],
            ['code' => 'bon_card', 'name' => 'بن‌کارت', 'type' => 'earning', 'calculation' => 'fixed', 'default_amount' => 0, 'is_insurable' => false, 'is_taxable' => true],
        ];

        foreach ($components as $row) {
            if (DB::table('hrm_payroll_components')->where('code', $row['code'])->exists()) {
                continue;
            }
            DB::table('hrm_payroll_components')->insert([
                ...$row,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $payslip = <<<'HTML'
<div dir="rtl" style="font-family:tahoma,sans-serif;max-width:720px">
  <h2>فیش حقوقی</h2>
  <p>{{employee_name}} — کد پرسنلی {{personnel_code}} — دوره {{period}}</p>
  <table style="width:100%;border-collapse:collapse" border="1" cellpadding="6">
    <tr><td>حقوق پایه</td><td>{{base}}</td></tr>
    <tr><td>مزایا و اضافات</td><td>{{earnings}}</td></tr>
    <tr><td>اضافه‌کار</td><td>{{overtime}}</td></tr>
    <tr><td>مأموریت</td><td>{{mission}}</td></tr>
    <tr><td>ناخالص</td><td>{{gross}}</td></tr>
    <tr><td>بیمه سهم کارگر (۷٪ پیش‌فرض)</td><td>{{employee_insurance}}</td></tr>
    <tr><td>بیمه سهم کارفرما (اطلاعاتی)</td><td>{{employer_insurance}}</td></tr>
    <tr><td>بیمه بیکاری (اطلاعاتی)</td><td>{{unemployment}}</td></tr>
    <tr><td>مالیات حقوق</td><td>{{tax}}</td></tr>
    <tr><td>وام</td><td>{{loan}}</td></tr>
    <tr><td>مساعده</td><td>{{advance}}</td></tr>
    <tr><td><strong>خالص پرداختی</strong></td><td><strong>{{net}}</strong></td></tr>
  </table>
  <p>مبالغ طبق تنظیمات حقوق (قابل ویرایش توسط مدیر منابع انسانی) محاسبه شده‌اند.</p>
</div>
HTML;

        $decree = <<<'HTML'
<div dir="rtl" style="font-family:tahoma,sans-serif;max-width:720px">
  <h2>حکم کارگزینی</h2>
  <p>شماره حکم: {{decree_no}}</p>
  <p>بدین‌وسیله حکم {{employee_name}} (کد پرسنلی {{personnel_code}}) به شرح زیر صادر می‌شود.</p>
  <ul>
    <li>سمت: {{job_title}}</li>
    <li>واحد: {{department}}</li>
    <li>نوع قرارداد: {{contract_type}}</li>
    <li>از تاریخ: {{effective_from}} تا: {{effective_to}}</li>
    <li>حقوق پایه: {{base_salary}}</li>
    <li>مزد روزانه: {{daily_wage}}</li>
  </ul>
</div>
HTML;

        foreach ([
            ['payslip', 'فیش حقوقی', $payslip],
            ['decree', 'حکم کارگزینی', $decree],
        ] as [$slug, $name, $html]) {
            if (DB::table('hrm_document_templates')->where('slug', $slug)->exists()) {
                continue;
            }
            DB::table('hrm_document_templates')->insert([
                'slug' => $slug,
                'name' => $name,
                'html' => $html,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_document_templates');
        Schema::dropIfExists('hrm_personnel_documents');
        Schema::dropIfExists('hrm_loan_installments');
        Schema::dropIfExists('hrm_loans');

        Schema::table('hrm_notices', function (Blueprint $table) {
            if (Schema::hasColumn('hrm_notices', 'employee_id')) {
                $table->dropConstrainedForeignId('employee_id');
            }
            if (Schema::hasColumn('hrm_notices', 'kind')) {
                $table->dropColumn('kind');
            }
        });

        Schema::table('hrm_payroll_items', function (Blueprint $table) {
            if (Schema::hasColumn('hrm_payroll_items', 'breakdown')) {
                $table->dropColumn('breakdown');
            }
        });

        Schema::table('hrm_payroll_components', function (Blueprint $table) {
            if (Schema::hasColumn('hrm_payroll_components', 'code')) {
                $table->dropUnique(['code']);
                $table->dropColumn(['code', 'is_insurable', 'is_taxable']);
            }
        });

        Schema::table('hrm_dependents', function (Blueprint $table) {
            if (Schema::hasColumn('hrm_dependents', 'is_insured')) {
                $table->dropColumn(['is_insured', 'coverage_start']);
            }
        });

        Schema::table('hrm_employees', function (Blueprint $table) {
            if (Schema::hasColumn('hrm_employees', 'contract_type')) {
                $table->dropColumn(['contract_type', 'contract_end_date', 'contract_status']);
            }
        });
    }
};

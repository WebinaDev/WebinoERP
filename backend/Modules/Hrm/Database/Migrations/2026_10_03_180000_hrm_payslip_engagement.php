<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hrm_employees', function (Blueprint $table) {
            if (! Schema::hasColumn('hrm_employees', 'engagement_type')) {
                $table->string('engagement_type', 30)->default('full_time')->after('status');
            }
            if (! Schema::hasColumn('hrm_employees', 'pay_basis')) {
                $table->string('pay_basis', 20)->default('monthly')->after('engagement_type');
            }
            if (! Schema::hasColumn('hrm_employees', 'project_fee')) {
                $table->decimal('project_fee', 15, 2)->default(0)->after('base_salary');
            }
            if (! Schema::hasColumn('hrm_employees', 'daily_rate')) {
                $table->decimal('daily_rate', 15, 2)->default(0)->after('project_fee');
            }
            if (! Schema::hasColumn('hrm_employees', 'hourly_rate')) {
                $table->decimal('hourly_rate', 15, 2)->default(0)->after('daily_rate');
            }
            if (! Schema::hasColumn('hrm_employees', 'insurance_applicable')) {
                $table->boolean('insurance_applicable')->default(true)->after('hourly_rate');
            }
            if (! Schema::hasColumn('hrm_employees', 'tax_applicable')) {
                $table->boolean('tax_applicable')->default(true)->after('insurance_applicable');
            }
        });

        Schema::table('hrm_employment_decrees', function (Blueprint $table) {
            if (! Schema::hasColumn('hrm_employment_decrees', 'engagement_type')) {
                $table->string('engagement_type', 30)->nullable()->after('contract_type');
            }
            if (! Schema::hasColumn('hrm_employment_decrees', 'pay_basis')) {
                $table->string('pay_basis', 20)->nullable()->after('engagement_type');
            }
            if (! Schema::hasColumn('hrm_employment_decrees', 'project_fee')) {
                $table->decimal('project_fee', 15, 2)->default(0)->after('daily_wage');
            }
            if (! Schema::hasColumn('hrm_employment_decrees', 'hourly_rate')) {
                $table->decimal('hourly_rate', 15, 2)->default(0)->after('project_fee');
            }
            if (! Schema::hasColumn('hrm_employment_decrees', 'insurance_applicable')) {
                $table->boolean('insurance_applicable')->default(true)->after('hourly_rate');
            }
            if (! Schema::hasColumn('hrm_employment_decrees', 'tax_applicable')) {
                $table->boolean('tax_applicable')->default(true)->after('insurance_applicable');
            }
            if (! Schema::hasColumn('hrm_employment_decrees', 'benefits')) {
                $table->json('benefits')->nullable()->after('tax_applicable');
            }
        });

        Schema::table('hrm_payroll_runs', function (Blueprint $table) {
            if (! Schema::hasColumn('hrm_payroll_runs', 'employee_id')) {
                $table->foreignId('employee_id')->nullable()->after('month')
                    ->constrained('hrm_employees')->nullOnDelete();
                $table->unique(['employee_id', 'year', 'month']);
            }
        });

        $extra = [
            'minimum_daily_wage' => 0,
            'minimum_hourly_wage' => 0,
            'working_days_per_month' => 30,
            'min_wage_engagements' => ['full_time'],
            'apply_benefits_engagements' => ['full_time', 'part_time', 'remote'],
            'min_wage_rule' => 'کف حقوق قانونی فقط برای نوع همکاری تمام‌وقت (و هر نوعی که در min_wage_engagements باشد) اعمال می‌شود. پیمانکاری، فریلنس، پروژه‌ای و دورکاری کف تمام‌وقت نمی‌گیرند مگر اینکه صریحاً به فهرست اضافه شوند. مبلغ ۰ یعنی کف هنوز تنظیم نشده و مانع ذخیره نیست. عیدی پیش‌فرض دو برابر آخرین مزد و حداکثر سه برابر حداقل ماهانه است.',
        ];
        foreach ($extra as $key => $value) {
            if (! DB::table('hrm_payroll_settings')->where('key', $key)->exists()) {
                DB::table('hrm_payroll_settings')->insert([
                    'key' => $key,
                    'value' => json_encode($value, JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $payslip = <<<'HTML'
<div dir="rtl" data-slip="ir-1405" style="font-family:tahoma,sans-serif;max-width:760px">
  <h2>فیش حقوقی</h2>
  <p>{{employee_name}} — کد پرسنلی {{personnel_code}} — دوره {{period}}</p>
  <p>نوع همکاری: {{engagement_type}} — مبنای پرداخت: {{pay_basis}}</p>
  <h3>مزایا</h3>
  <table style="width:100%;border-collapse:collapse" border="1" cellpadding="6">
    <tr><td>حقوق / دستمزد مبنا</td><td>{{base}}</td></tr>
    <tr><td>بن خواربار</td><td>{{line_bon}}</td></tr>
    <tr><td>حق مسکن</td><td>{{line_housing}}</td></tr>
    <tr><td>حق اولاد</td><td>{{line_child}}</td></tr>
    <tr><td>پایه سنوات</td><td>{{line_seniority}}</td></tr>
    <tr><td>پاداش</td><td>{{line_reward}}</td></tr>
    <tr><td>عیدی</td><td>{{line_eydi}}</td></tr>
    <tr><td>بن‌کارت</td><td>{{line_bon_card}}</td></tr>
    <tr><td>اضافه‌کار</td><td>{{overtime}}</td></tr>
    <tr><td>مأموریت</td><td>{{mission}}</td></tr>
    <tr><td>ناخالص</td><td>{{gross}}</td></tr>
  </table>
  <h3>کسورات و بیمه</h3>
  <table style="width:100%;border-collapse:collapse" border="1" cellpadding="6">
    <tr><td>مشمول بیمه</td><td>{{insurable}}</td></tr>
    <tr><td>تأمین اجتماعی سهم کارگر</td><td>{{employee_insurance}}</td></tr>
    <tr><td>تأمین اجتماعی سهم کارفرما (اطلاعاتی، کسر نمی‌شود)</td><td>{{employer_insurance}}</td></tr>
    <tr><td>بیمه بیکاری (اطلاعاتی)</td><td>{{unemployment}}</td></tr>
    <tr><td>مالیات حقوق (پلکانی)</td><td>{{tax}}</td></tr>
    <tr><td>قسط وام</td><td>{{loan}}</td></tr>
    <tr><td>مساعده</td><td>{{advance}}</td></tr>
    <tr><td>سایر کسور</td><td>{{other_deductions}}</td></tr>
    <tr><td>جمع کسور</td><td>{{deductions}}</td></tr>
    <tr><td><strong>خالص پرداختی</strong></td><td><strong>{{net}}</strong></td></tr>
  </table>
  <p>بیمه مشمول: {{insurance_applicable}} — مالیات مشمول: {{tax_applicable}}</p>
  <p>نرخ‌ها از تنظیمات حقوق خوانده می‌شوند (پیش‌فرض تأمین اجتماعی ۷٪ کارگر / ۲۰٪ کارفرما / ۳٪ بیکاری، اضافه‌کار ۱٫۴، پلکان بودجه ۱۴۰۵ به ریال).</p>
</div>
HTML;

        $decree = <<<'HTML'
<div dir="rtl" data-slip="ir-1405" style="font-family:tahoma,sans-serif;max-width:760px">
  <h2>حکم کارگزینی</h2>
  <p>شماره حکم: {{decree_no}}</p>
  <p>{{employee_name}} — کد پرسنلی {{personnel_code}}</p>
  <ul>
    <li>سمت: {{job_title}}</li>
    <li>واحد: {{department}}</li>
    <li>نوع قرارداد: {{contract_type}}</li>
    <li>نوع همکاری: {{engagement_type}}</li>
    <li>مبنای پرداخت: {{pay_basis}}</li>
    <li>از {{effective_from}} تا {{effective_to}}</li>
    <li>حقوق ماهانه: {{base_salary}}</li>
    <li>مزد روزانه: {{daily_wage}}</li>
    <li>نرخ ساعتی: {{hourly_rate}}</li>
    <li>حق‌الزحمه / مبلغ پروژه: {{project_fee}}</li>
    <li>مشمول تأمین اجتماعی: {{insurance_applicable}}</li>
    <li>مشمول مالیات حقوق: {{tax_applicable}}</li>
  </ul>
  <h3>اجزای مزدی حکم</h3>
  <pre>{{benefits_rows}}</pre>
  <p>کف مزد قانونی فقط وقتی اعمال می‌شود که نوع همکاری در فهرست min_wage_engagements باشد (پیش‌فرض: تمام‌وقت).</p>
</div>
HTML;

        foreach (['payslip' => $payslip, 'decree' => $decree] as $slug => $html) {
            $row = DB::table('hrm_document_templates')->where('slug', $slug)->first();
            if (! $row) {
                DB::table('hrm_document_templates')->insert([
                    'slug' => $slug,
                    'name' => $slug === 'payslip' ? 'فیش حقوقی' : 'حکم کارگزینی',
                    'html' => $html,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                continue;
            }
            if (! str_contains((string) $row->html, 'data-slip="ir-1405"')) {
                DB::table('hrm_document_templates')->where('slug', $slug)->update([
                    'html' => $html,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('hrm_payroll_runs', function (Blueprint $table) {
            if (Schema::hasColumn('hrm_payroll_runs', 'employee_id')) {
                $table->dropUnique(['employee_id', 'year', 'month']);
                $table->dropConstrainedForeignId('employee_id');
            }
        });
        Schema::table('hrm_employment_decrees', function (Blueprint $table) {
            foreach (['benefits', 'tax_applicable', 'insurance_applicable', 'hourly_rate', 'project_fee', 'pay_basis', 'engagement_type'] as $col) {
                if (Schema::hasColumn('hrm_employment_decrees', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        Schema::table('hrm_employees', function (Blueprint $table) {
            foreach (['tax_applicable', 'insurance_applicable', 'hourly_rate', 'daily_rate', 'project_fee', 'pay_basis', 'engagement_type'] as $col) {
                if (Schema::hasColumn('hrm_employees', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

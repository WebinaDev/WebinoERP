<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Official Iranian labor year 1405 (2026–27) statutory defaults + remaining HRM gaps:
 * multi-step approval columns, document serials/signatures, payroll→accounting hook fields.
 *
 * Sources (public): شورای عالی کار / بخشنامه مزد ۱۴۰۵ via kartaban.com, hesabamooz.com, ravihesab.com
 * (daily min 5,541,850 Rials; monthly 166,255,500; بن 22M; مسکن 30M; اولاد 16,625,550; سنوات روزانه 166,667;
 * حق تاهل 5M; hourly ~756,050). Tax ladder from بودجه ۱۴۰۵ (ماهانه معاف تا ۴۰۰ میلیون ریال).
 * SSO rates remain worker 7% / employer 20% / unemployment 3%.
 * Values labeled law_year=1405; HR may override via payroll settings API.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hrm_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('hrm_requests', 'approval_step')) {
                $table->unsignedTinyInteger('approval_step')->default(1)->after('status');
            }
            if (! Schema::hasColumn('hrm_requests', 'current_role')) {
                $table->string('current_role', 40)->nullable()->after('approval_step');
            }
            if (! Schema::hasColumn('hrm_requests', 'approval_log')) {
                $table->json('approval_log')->nullable()->after('current_role');
            }
        });

        Schema::table('hrm_leave_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('hrm_leave_requests', 'approval_step')) {
                $table->unsignedTinyInteger('approval_step')->default(1)->after('status');
            }
            if (! Schema::hasColumn('hrm_leave_requests', 'current_role')) {
                $table->string('current_role', 40)->nullable()->after('approval_step');
            }
            if (! Schema::hasColumn('hrm_leave_requests', 'approval_log')) {
                $table->json('approval_log')->nullable()->after('current_role');
            }
        });

        Schema::table('hrm_payroll_runs', function (Blueprint $table) {
            if (! Schema::hasColumn('hrm_payroll_runs', 'journal_entry_id')) {
                $table->unsignedBigInteger('journal_entry_id')->nullable()->after('total_amount');
            }
            if (! Schema::hasColumn('hrm_payroll_runs', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('journal_entry_id');
            }
            if (! Schema::hasColumn('hrm_payroll_runs', 'serial_no')) {
                $table->string('serial_no', 40)->nullable()->after('paid_at');
            }
        });

        Schema::table('hrm_employment_decrees', function (Blueprint $table) {
            if (! Schema::hasColumn('hrm_employment_decrees', 'serial_no')) {
                $table->string('serial_no', 40)->nullable()->after('decree_no');
            }
            if (! Schema::hasColumn('hrm_employment_decrees', 'signer_name')) {
                $table->string('signer_name', 150)->nullable()->after('serial_no');
            }
            if (! Schema::hasColumn('hrm_employment_decrees', 'signer_role')) {
                $table->string('signer_role', 100)->nullable()->after('signer_name');
            }
            if (! Schema::hasColumn('hrm_employment_decrees', 'stamp_path')) {
                $table->string('stamp_path', 255)->nullable()->after('signer_role');
            }
        });

        Schema::table('hrm_payroll_items', function (Blueprint $table) {
            if (! Schema::hasColumn('hrm_payroll_items', 'serial_no')) {
                $table->string('serial_no', 40)->nullable()->after('breakdown');
            }
        });

        if (! Schema::hasTable('hrm_serial_sequences')) {
            Schema::create('hrm_serial_sequences', function (Blueprint $table) {
                $table->id();
                $table->string('scope', 40)->unique(); // payslip|decree|payroll_run
                $table->unsignedBigInteger('next_number')->default(1);
                $table->string('prefix', 20)->default('');
                $table->timestamps();
            });
            foreach (['payslip' => 'PS-', 'decree' => 'DC-', 'payroll_run' => 'PR-'] as $scope => $prefix) {
                DB::table('hrm_serial_sequences')->insert([
                    'scope' => $scope,
                    'next_number' => 1,
                    'prefix' => $prefix,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->seed1405();
        $this->patchTemplates();
    }

    private function seed1405(): void
    {
        // All amounts in Rials. law_year meta documents the statutory year as ۱۴۰۵.
        $settings = [
            'law_year' => 1405,
            'law_year_label' => '۱۴۰۵',
            'law_year_note' => 'مقادیر پیش‌فرض مصوبه مزد / بودجه ۱۴۰۵ (ریال). قابل ویرایش در تنظیمات حقوق.',
            'employee_insurance_percent' => 7,
            'employer_insurance_percent' => 20,
            'unemployment_insurance_percent' => 3,
            'insurance_ceiling' => 0,
            'overtime_multiplier' => 1.4,
            'monthly_hours' => 220,
            'working_days_per_month' => 30,
            'mission_daily_allowance' => 0,
            // حداقل مزد روزانه / ماهانه / ساعتی ۱۴۰۵
            'minimum_daily_wage' => 5541850,
            'minimum_monthly_wage' => 166255500,
            'minimum_hourly_wage' => 756050,
            // پایه سنوات روزانه ۱۴۰۵
            'seniority_daily_rate' => 166667,
            'seniority_monthly_amount' => 5000000,
            // عیدی: دو برابر مزد، سقف ۳ برابر حداقل ماهانه، تناسب ماه کارکرد
            'eydi_month' => 12,
            'eydi_months_factor' => 2,
            'eydi_cap_min_wage_months' => 3,
            'eydi_prorate' => true,
            // سنوات پایان خدمت: یک ماه آخرین مزد به ازای هر سال سابقه (تناسب ماه)
            'severance_months_per_year' => 1,
            'severance_prorate' => true,
            'insurance_deductible_fraction' => 0.285714,
            'min_wage_engagements' => ['full_time'],
            'apply_benefits_engagements' => ['full_time', 'part_time', 'remote'],
            'approval_chain' => ['manager', 'hr', 'finance'],
            'approval_role_map' => [
                'manager' => ['project_manager', 'system_manager'],
                'hr' => ['hr_manager', 'system_manager'],
                'finance' => ['finance_manager', 'system_manager'],
            ],
            // Accounting hook account codes (Iran chart); bridge no-ops if missing
            'payroll_expense_account_code' => '5',
            'payroll_payable_account_code' => '211',
            'insurance_payable_account_code' => '211',
            'tax_payable_account_code' => '211',
            'payroll_accounting_enabled' => true,
            'external_notifications_enabled' => true,
            'tax_brackets' => [
                ['up_to' => 400000000, 'rate' => 0],
                ['up_to' => 800000000, 'rate' => 10],
                ['up_to' => 1000000000, 'rate' => 15],
                ['up_to' => 1200000000, 'rate' => 20],
                ['up_to' => 1400000000, 'rate' => 25],
                ['up_to' => null, 'rate' => 30],
            ],
            'min_wage_rule' => 'کف حقوق قانونی ۱۴۰۵ فقط برای نوع همکاری تمام‌وقت (و هر نوعی در min_wage_engagements). عیدی = ۲×مزد با سقف ۳×حداقل ماهانه و تناسب ماه کارکرد. سنوات پایان خدمت = ۱ ماه آخرین مزد × سال سابقه (تناسب ماه).',
        ];

        foreach ($settings as $key => $value) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);
            $exists = DB::table('hrm_payroll_settings')->where('key', $key)->exists();
            if ($exists) {
                DB::table('hrm_payroll_settings')->where('key', $key)->update([
                    'value' => $encoded,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('hrm_payroll_settings')->insert([
                    'key' => $key,
                    'value' => $encoded,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $components = [
            ['code' => 'bon', 'name' => 'بن کارگری (خواربار) ۱۴۰۵', 'type' => 'earning', 'calculation' => 'fixed', 'default_amount' => 22000000, 'is_insurable' => true, 'is_taxable' => false],
            ['code' => 'housing', 'name' => 'حق مسکن ۱۴۰۵', 'type' => 'earning', 'calculation' => 'fixed', 'default_amount' => 30000000, 'is_insurable' => true, 'is_taxable' => false],
            ['code' => 'child', 'name' => 'حق اولاد ۱۴۰۵ (هر فرزند)', 'type' => 'earning', 'calculation' => 'per_insured_dependent', 'default_amount' => 16625550, 'is_insurable' => false, 'is_taxable' => false],
            ['code' => 'seniority', 'name' => 'پایه سنوات ۱۴۰۵', 'type' => 'earning', 'calculation' => 'fixed', 'default_amount' => 5000000, 'is_insurable' => true, 'is_taxable' => true],
            ['code' => 'marital', 'name' => 'حق تاهل ۱۴۰۵', 'type' => 'earning', 'calculation' => 'marital', 'default_amount' => 5000000, 'is_insurable' => false, 'is_taxable' => false],
            ['code' => 'reward', 'name' => 'پاداش', 'type' => 'earning', 'calculation' => 'fixed', 'default_amount' => 0, 'is_insurable' => false, 'is_taxable' => true],
            ['code' => 'eydi', 'name' => 'عیدی و پاداش پایان سال ۱۴۰۵', 'type' => 'earning', 'calculation' => 'eydi', 'default_amount' => 0, 'is_insurable' => false, 'is_taxable' => false],
            ['code' => 'bon_card', 'name' => 'بن‌کارت', 'type' => 'earning', 'calculation' => 'fixed', 'default_amount' => 0, 'is_insurable' => false, 'is_taxable' => true],
            ['code' => 'severance', 'name' => 'سنوات پایان خدمت', 'type' => 'earning', 'calculation' => 'severance', 'default_amount' => 0, 'is_insurable' => false, 'is_taxable' => true],
        ];

        foreach ($components as $row) {
            $existing = DB::table('hrm_payroll_components')->where('code', $row['code'])->first();
            $payload = [
                ...$row,
                'is_active' => true,
                'updated_at' => now(),
            ];
            if ($existing) {
                DB::table('hrm_payroll_components')->where('id', $existing->id)->update($payload);
            } else {
                DB::table('hrm_payroll_components')->insert([
                    ...$payload,
                    'created_at' => now(),
                ]);
            }
        }
    }

    private function patchTemplates(): void
    {
        $sig = <<<'HTML'
  <div style="margin-top:28px;display:flex;justify-content:space-between;gap:24px">
    <div style="flex:1;text-align:center;border-top:1px solid #333;padding-top:8px">
      <div>مهر سازمان</div>
      <div style="min-height:64px;border:1px dashed #999;margin:8px auto;width:120px">{{stamp_placeholder}}</div>
    </div>
    <div style="flex:1;text-align:center;border-top:1px solid #333;padding-top:8px">
      <div>امضا</div>
      <div>{{signer_name}}</div>
      <div>{{signer_role}}</div>
    </div>
  </div>
  <p>شماره سریال سازمانی: {{serial_no}} — سال قانون: {{law_year_label}}</p>
HTML;

        foreach (['payslip', 'decree'] as $slug) {
            $row = DB::table('hrm_document_templates')->where('slug', $slug)->first();
            if (! $row) {
                continue;
            }
            $html = (string) $row->html;
            if (! str_contains($html, '{{serial_no}}')) {
                $html = rtrim($html);
                if (str_ends_with($html, '</div>')) {
                    $html = substr($html, 0, -6).$sig."\n</div>";
                } else {
                    $html .= "\n".$sig;
                }
                DB::table('hrm_document_templates')->where('slug', $slug)->update([
                    'html' => $html,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hrm_serial_sequences');
        Schema::table('hrm_payroll_items', function (Blueprint $table) {
            if (Schema::hasColumn('hrm_payroll_items', 'serial_no')) {
                $table->dropColumn('serial_no');
            }
        });
        Schema::table('hrm_employment_decrees', function (Blueprint $table) {
            foreach (['stamp_path', 'signer_role', 'signer_name', 'serial_no'] as $col) {
                if (Schema::hasColumn('hrm_employment_decrees', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        Schema::table('hrm_payroll_runs', function (Blueprint $table) {
            foreach (['serial_no', 'paid_at', 'journal_entry_id'] as $col) {
                if (Schema::hasColumn('hrm_payroll_runs', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        Schema::table('hrm_leave_requests', function (Blueprint $table) {
            foreach (['approval_log', 'current_role', 'approval_step'] as $col) {
                if (Schema::hasColumn('hrm_leave_requests', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        Schema::table('hrm_requests', function (Blueprint $table) {
            foreach (['approval_log', 'current_role', 'approval_step'] as $col) {
                if (Schema::hasColumn('hrm_requests', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

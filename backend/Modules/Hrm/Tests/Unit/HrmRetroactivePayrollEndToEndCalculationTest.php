<?php

namespace Modules\Hrm\Tests\Unit;

use Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Entities\AccChartAccount;
use Modules\Accounting\Entities\AccJournalEntry;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmEmploymentDecree;
use Modules\Hrm\Entities\HrmPayrollItem;
use Modules\Hrm\Entities\HrmPayrollRetroAdjustment;
use Modules\Hrm\Entities\HrmPayrollRun;
use Modules\Hrm\Services\HrmPayrollAccountingBridge;
use Modules\Hrm\Services\HrmRetroactivePayrollService;
use Modules\Hrm\Services\IranianPayrollCalculator;

class HrmRetroactivePayrollEndToEndCalculationTest extends TestCase
{
    use RefreshDatabase;

    public function test_retroactive_decree_calculates_differentials_and_segregates_in_payroll_and_accounting(): void
    {
        $calc = app(IranianPayrollCalculator::class);
        $retroService = app(HrmRetroactivePayrollService::class);
        $bridge = app(HrmPayrollAccountingBridge::class);

        // 1. Seed Chart of Accounts for segregated Accounting Bridge
        AccChartAccount::query()->create(['code' => '510101', 'name' => 'هزینه حقوق و دستمزد جاری', 'type' => 'expense', 'is_postable' => true]);
        AccChartAccount::query()->create(['code' => '510102', 'name' => 'هزینه معوقات احکام سال جاری', 'type' => 'expense', 'is_postable' => true]);
        AccChartAccount::query()->create(['code' => '310201', 'name' => 'تعدیلات سنواتی سنوات قبل', 'type' => 'equity', 'is_postable' => true]);
        AccChartAccount::query()->create(['code' => '510103', 'name' => 'هزینه بیمه سهم کارفرما', 'type' => 'expense', 'is_postable' => true]);
        AccChartAccount::query()->create(['code' => '210201', 'name' => 'بیمه پرداختنی تأمین اجتماعی', 'type' => 'liability', 'is_postable' => true]);
        AccChartAccount::query()->create(['code' => '210204', 'name' => 'بیمه سهم کارگر پرداختنی', 'type' => 'liability', 'is_postable' => true]);
        AccChartAccount::query()->create(['code' => '210202', 'name' => 'مالیات تکلیفی حقوق پرداختنی', 'type' => 'liability', 'is_postable' => true]);
        AccChartAccount::query()->create(['code' => '210203', 'name' => 'کسور اقساط وام و مساعده', 'type' => 'liability', 'is_postable' => true]);
        AccChartAccount::query()->create(['code' => '210301', 'name' => 'حقوق پرداختنی پرسنل', 'type' => 'liability', 'is_postable' => true]);

        // 2. Create Employee: base_salary = 100,000,000 IRR
        $employee = HrmEmployee::query()->create([
            'employee_code' => 'EMP-RETRO-EXACT',
            'first_name' => 'مسعود',
            'last_name' => 'بهبهانی',
            'national_id' => '0076543210',
            'base_salary' => 100000000,
            'engagement_type' => 'full_time',
            'pay_basis' => 'monthly',
            'is_active' => true,
        ]);

        // 3. Create Past Historical Payroll Run (Month 1, Year 1405): paid under old base (100M)
        $pastRun = HrmPayrollRun::query()->create([
            'title' => 'حقوق فروردین ۱۴۰۵',
            'year' => 1405,
            'month' => 1,
            'status' => 'paid',
        ]);

        // Past Gross: 100M base + 14M housing + 18M bon = 132,000,000 IRR
        // Past Insurance: 132M * 7% = 9,240,000 IRR
        // Past Net = 132,000,000 - 9,240,000 = 122,760,000 IRR
        HrmPayrollItem::query()->create([
            'payroll_run_id' => $pastRun->id,
            'employee_id' => $employee->id,
            'gross' => 132000000,
            'net' => 122760000,
            'employee_insurance' => 9240000,
            'employer_insurance' => 26400000,
            'tax' => 0,
            'deductions' => 9240000,
        ]);

        // 4. Issue Retroactive Employment Decree with effective_from in Month 1 (2026-03-21)
        // Increases base salary from 100M to 150M (+50M base differential)
        $decree = HrmEmploymentDecree::query()->create([
            'employee_id' => $employee->id,
            'decree_no' => 'DEC-RETRO-001',
            'decree_type' => 'اصلاحیه ارتقاء رتبه شغلی',
            'status' => 'approved',
            'effective_from' => '2026-03-21',
            'created_at' => '2026-04-25 10:00:00', // Issued in Month 2
            'base_salary' => 150000000,
            'daily_wage' => 5000000,
        ]);

        // 5. Trigger calculateRetroDifferentials
        $adjustments = $retroService->calculateRetroDifferentials($decree);

        $this->assertCount(1, $adjustments);
        $adj = $adjustments[0];

        // Adjusted Gross for Month 1 = 150M base + 14M housing + 18M bon = 182,000,000 IRR
        // Differential Gross = 182M - 132M = 50,000,000 IRR
        $this->assertEquals(50000000.0, (float) $adj->differential_amount);

        // Insurance Diff = (182M * 7% = 12,740,000) - 9,240,000 = 3,500,000 IRR
        $this->assertEquals(3500000.0, (float) $adj->differential_insurance);

        // Net Diff = 50,000,000 - 3,500,000 = 46,500,000 IRR
        $this->assertEquals(46500000.0, (float) $adj->differential_net);
        $this->assertEquals(1405, $adj->target_year);
        $this->assertEquals(1, $adj->target_month);

        // 6. Current Month 2 Payroll Calculation (1405/02):
        // Current Base: 150M + Housing: 14M + Bon: 18M = 182M
        // Retro differential from Month 1 = 50M
        // Total Gross = 182M + 50M = 232,000,000 IRR
        $currentRun = HrmPayrollRun::query()->create([
            'title' => 'حقوق اردیبهشت ۱۴۰۵',
            'year' => 1405,
            'month' => 2,
            'status' => 'approved',
        ]);

        $payroll = $calc->forEmployee($employee, 1405, 2, $currentRun->id);

        $this->assertEquals(50000000.0, $payroll['breakdown']['retro_current_year']);
        $this->assertEquals(0.0, $payroll['breakdown']['retro_prior_years']);
        $this->assertEquals(50000000.0, $payroll['breakdown']['retro_total']);
        $this->assertEquals(232000000.0, $payroll['gross']);

        // Check deduction & net:
        // Regular insurance = min(182M, ceiling 221.55M) * 7% = 12,740,000
        // Retro insurance = 3,500,000
        // Total employee insurance = 12,740,000 + 3,500,000 = 16,240,000 IRR
        $this->assertEquals(16240000.0, $payroll['breakdown']['employee_insurance']);

        // Net = 232,000,000 - 16,240,000 = 215,760,000 IRR
        $this->assertEquals(215760000.0, $payroll['net']);

        // 7. Verify Segregated Accounting Voucher via HrmPayrollAccountingBridge
        HrmPayrollItem::query()->create([
            'payroll_run_id' => $currentRun->id,
            'employee_id' => $employee->id,
            'gross' => $payroll['gross'],
            'net' => $payroll['net'],
            'deductions' => $payroll['deductions'],
            'employee_insurance' => $payroll['breakdown']['employee_insurance'],
            'employer_insurance' => $payroll['breakdown']['employer_insurance'],
            'tax' => $payroll['breakdown']['tax'],
        ]);

        $postResult = $bridge->postPayrollRun($currentRun);
        $this->assertTrue($postResult['ok']);

        $je = AccJournalEntry::query()->with('lines')->find($postResult['journal_entry_id']);
        $this->assertNotNull($je);

        $lines = $je->lines;
        $retroLine = $lines->firstWhere('account_id', AccChartAccount::query()->where('code', '510102')->value('id'));

        $this->assertNotNull($retroLine, 'Segregated retro accounting line must exist');
        $this->assertEquals(50000000.0, (float) $retroLine->debit);
        $this->assertEquals(0.0, (float) $retroLine->credit);

        // Check Double-entry balance
        $totalDebit = (float) $lines->sum('debit');
        $totalCredit = (float) $lines->sum('credit');
        $this->assertEquals($totalDebit, $totalCredit);
    }
}

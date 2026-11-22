<?php

namespace Modules\Hrm\Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Entities\AccChartAccount;
use Modules\Accounting\Entities\AccJournalEntry;
use Modules\Accounting\Entities\AccJournalLine;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmPayrollItem;
use Modules\Hrm\Entities\HrmPayrollRetroAdjustment;
use Modules\Hrm\Entities\HrmPayrollRun;
use Modules\Hrm\Services\HrmPayrollAccountingBridge;

class HrmPayrollAccountingSegregationTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_payroll_run_creates_full_segregated_journal_lines(): void
    {
        // 1. Create Chart of Accounts Nodes
        AccChartAccount::query()->create(['code' => '510101', 'name' => 'هزینه حقوق پایه', 'type' => 'expense', 'is_postable' => true]);
        AccChartAccount::query()->create(['code' => '510102', 'name' => 'هزینه معوقات سال جاری', 'type' => 'expense', 'is_postable' => true]);
        AccChartAccount::query()->create(['code' => '310201', 'name' => 'تعدیلات سنواتی سنوات قبل', 'type' => 'equity', 'is_postable' => true]);
        AccChartAccount::query()->create(['code' => '510103', 'name' => 'هزینه بیمه کارفرما', 'type' => 'expense', 'is_postable' => true]);
        AccChartAccount::query()->create(['code' => '210201', 'name' => 'بیمه پرداختنی تأمین اجتماعی', 'type' => 'liability', 'is_postable' => true]);
        AccChartAccount::query()->create(['code' => '210204', 'name' => 'بیمه سهم کارگر پرداختنی', 'type' => 'liability', 'is_postable' => true]);
        AccChartAccount::query()->create(['code' => '210202', 'name' => 'مالیات تکلیفی حقوق پرداختنی', 'type' => 'liability', 'is_postable' => true]);
        AccChartAccount::query()->create(['code' => '210203', 'name' => 'کسور اقساط وام پرسنلی', 'type' => 'liability', 'is_postable' => true]);
        AccChartAccount::query()->create(['code' => '210301', 'name' => 'حقوق پرداختنی پرسنل', 'type' => 'liability', 'is_postable' => true]);

        // 2. Create Employee
        $employee = HrmEmployee::query()->create([
            'employee_code' => 'EMP-ACCT-01',
            'first_name' => 'سعید',
            'last_name' => 'یزدانی',
            'national_id' => '1234567890',
            'base_salary' => 200000000,
            'is_active' => true,
        ]);

        // 3. Create Payroll Run
        $run = HrmPayrollRun::query()->create([
            'title' => 'حقوق و معوقات اردیبهشت',
            'year' => 1405,
            'month' => 2,
            'status' => 'approved',
        ]);

        // Create Payroll Item:
        // Gross = 250,000,000 (Base: 210M + Current Retro: 25M + Prior Retro: 15M)
        // Employee Ins = 15,000,000
        // Employer Ins = 48,000,000
        // Tax = 5,000,000
        // Loan = 10,000,000
        // Deductions = 15M + 5M + 10M = 30,000,000
        // Net = 250M - 30M = 220,000,000
        HrmPayrollItem::query()->create([
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'gross' => 250000000,
            'net' => 220000000,
            'deductions' => 30000000,
            'employee_insurance' => 15000000,
            'employer_insurance' => 48000000,
            'tax' => 5000000,
            'taxable_wage' => 250000000,
            'insurable_wage' => 221550000,
        ]);

        // 4. Create Retro Adjustments
        HrmPayrollRetroAdjustment::query()->create([
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'target_year' => 1405,
            'target_month' => 1,
            'differential_amount' => 25000000,
        ]);

        HrmPayrollRetroAdjustment::query()->create([
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'target_year' => 1404,
            'target_month' => 12,
            'differential_amount' => 15000000,
        ]);

        $bridge = app(HrmPayrollAccountingBridge::class);
        $result = $bridge->postPayrollRun($run);

        $this->assertTrue($result['ok'], 'Accounting post should succeed: ' . ($result['message'] ?? ''));
        $this->assertNotNull($result['journal_entry_id']);

        $je = AccJournalEntry::query()->with('lines')->find($result['journal_entry_id']);
        $this->assertNotNull($je);

        $lines = $je->lines;

        // Verify segregated lines count and line types:
        // Expected segregated lines:
        // Debits (4 lines):
        // 1. Current gross wage expense (210,000,000)
        // 2. Current year retro differential (25,000,000)
        // 3. Prior year retro adjustment (15,000,000)
        // 4. Employer insurance expense (48,000,000)
        // Credits (5 lines):
        // 5. Employer insurance payable (48,000,000)
        // 6. Employee insurance payable (15,000,000)
        // 7. Tax payable (5,000,000)
        // 8. Loan deductions (10,000,000)
        // 9. Net salary payable (220,000,000)
        $this->assertCount(9, $lines);

        $descriptions = $lines->pluck('description')->all();

        $this->assertTrue(collect($descriptions)->contains(fn ($d) => str_contains($d, 'هزینه حقوق و دستمزد جاری')));
        $this->assertTrue(collect($descriptions)->contains(fn ($d) => str_contains($d, 'معوقات احکام سال جاری')));
        $this->assertTrue(collect($descriptions)->contains(fn ($d) => str_contains($d, 'معوقات احکام سنوات قبل')));
        $this->assertTrue(collect($descriptions)->contains(fn ($d) => str_contains($d, 'هزینه بیمه سهم کارفرما')));
        $this->assertTrue(collect($descriptions)->contains(fn ($d) => str_contains($d, 'حق بیمه سهم کارگر پرداختنی')));
        $this->assertTrue(collect($descriptions)->contains(fn ($d) => str_contains($d, 'مالیات تکلیفی حقوق پرداختنی')));
        $this->assertTrue(collect($descriptions)->contains(fn ($d) => str_contains($d, 'کسور اقساط وام')));
        $this->assertTrue(collect($descriptions)->contains(fn ($d) => str_contains($d, 'خالص حقوق پرداختنی پرسنل')));

        // Check exact accounting balance: Debit sum == Credit sum
        $totalDebit = round((float) $lines->sum('debit'), 2);
        $totalCredit = round((float) $lines->sum('credit'), 2);

        $this->assertEquals($totalDebit, $totalCredit);
        $this->assertEquals(298000000.0, $totalDebit);
    }

    public function test_post_payroll_run_fails_with_clear_error_when_chart_account_is_missing(): void
    {
        // No chart accounts seeded
        $employee = HrmEmployee::query()->create([
            'employee_code' => 'EMP-MISSING',
            'first_name' => 'احمد',
            'last_name' => 'حیدری',
            'national_id' => '1234567899',
            'base_salary' => 100000000,
            'is_active' => true,
        ]);

        $run = HrmPayrollRun::query()->create([
            'title' => 'حقوق بدون حساب معین',
            'year' => 1405,
            'month' => 3,
            'status' => 'approved',
        ]);

        HrmPayrollItem::query()->create([
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'gross' => 100000000,
            'net' => 93000000,
            'deductions' => 7000000,
            'employee_insurance' => 7000000,
            'employer_insurance' => 23000000,
            'tax' => 0,
        ]);

        $bridge = app(HrmPayrollAccountingBridge::class);
        $result = $bridge->postPayrollRun($run);

        $this->assertFalse($result['ok']);
        $this->assertNull($result['journal_entry_id']);
        $this->assertStringContainsString('تعریف نشده‌اند', $result['message']);
        $this->assertStringContainsString('510101', $result['message']);
    }
}

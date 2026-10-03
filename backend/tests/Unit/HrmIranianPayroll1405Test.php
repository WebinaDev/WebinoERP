<?php

namespace Tests\Unit;

use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Services\IranianPayrollCalculator;
use PHPUnit\Framework\TestCase;

class HrmIranianPayroll1405Test extends TestCase
{
    public function test_defaults_are_labeled_1405(): void
    {
        $calc = new IranianPayrollCalculator;
        $d = $calc->defaults();
        $this->assertSame(1405, $d['law_year']);
        $this->assertSame(166255500, $d['minimum_monthly_wage']);
        $this->assertSame(5541850, $d['minimum_daily_wage']);
        $this->assertSame(756050, $d['minimum_hourly_wage']);
        $this->assertSame(7, $d['employee_insurance_percent']);
        $this->assertSame(20, $d['employer_insurance_percent']);
        $this->assertSame(3, $d['unemployment_insurance_percent']);
    }

    public function test_eydi_cap_three_times_minimum(): void
    {
        $calc = new IranianPayrollCalculator;
        $settings = $calc->defaults();
        $settings['eydi_prorate'] = false;
        $high = $calc->eydiAmount(300000000, $settings, null);
        $this->assertEquals(166255500 * 3, $high);
        $low = $calc->eydiAmount(100000000, $settings, null);
        $this->assertEquals(200000000, $low);
    }
}

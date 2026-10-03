<?php

namespace Tests\Unit;

use App\Support\CalendarDate;
use PHPUnit\Framework\TestCase;

class CalendarDateTest extends TestCase
{
    public function test_nowruz_round_trip(): void
    {
        $this->assertSame(['jy' => 1405, 'jm' => 1, 'jd' => 1], CalendarDate::toJalali(2026, 3, 21));
        $this->assertSame(['gy' => 2026, 'gm' => 3, 'gd' => 21], CalendarDate::toGregorian(1405, 1, 1));
        $this->assertSame(['jy' => 1403, 'jm' => 1, 'jd' => 1], CalendarDate::toJalali(2024, 3, 20));
    }

    public function test_formats_jalali_digits_for_fa_and_gregorian_for_en(): void
    {
        $this->assertSame('۱۴۰۵/۰۱/۰۱', CalendarDate::format('2026-03-21', 'fa'));
        $this->assertSame('2026-03-21', CalendarDate::format('2026-03-21', 'en'));
    }

    public function test_shifts_utc_instants_into_tehran_for_persian_display(): void
    {
        $this->assertSame('۱۴۰۵/۰۱/۰۱ ۰۰:۰۰', CalendarDate::format('2026-03-20T20:30:00Z', 'fa', true));
        $this->assertSame('2026-03-20 20:30', CalendarDate::format('2026-03-20T20:30:00Z', 'en', true));
    }

    public function test_date_only_values_are_not_timezone_shifted(): void
    {
        $this->assertSame('۱۴۰۵/۰۱/۰۱', CalendarDate::format('2026-03-21', 'fa', true));
        $this->assertSame('2026-03-21', CalendarDate::format('2026-03-21', 'en', true));
    }
}

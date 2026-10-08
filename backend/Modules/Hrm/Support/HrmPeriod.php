<?php

namespace Modules\Hrm\Support;

use App\Support\CalendarDate;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Payroll periods are stored as year/month. Years below 1700 are Jalali (۱۴۰۵),
 * otherwise Gregorian. These helpers keep the two calendars consistent.
 */
class HrmPeriod
{
    public static function isJalali(int $year): bool
    {
        return $year > 0 && $year < 1700;
    }

    /**
     * @return array{0: int, 1: int}
     */
    public static function currentJalali(): array
    {
        $now = now('Asia/Tehran');
        $j = CalendarDate::toJalali((int) $now->year, (int) $now->month, (int) $now->day);

        return [$j['jy'], $j['jm']];
    }

    /**
     * Jalali year/month for a stored period (Gregorian periods use their first day).
     *
     * @return array{0: int, 1: int}
     */
    public static function toJalaliPeriod(int $year, int $month): array
    {
        if (self::isJalali($year)) {
            return [$year, $month];
        }
        $j = CalendarDate::toJalali($year, max(1, $month), 1);

        return [$j['jy'], $j['jm']];
    }

    /**
     * Gregorian date range covered by a stored period.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function gregorianRange(int $year, int $month): array
    {
        $month = max(1, min(12, $month));
        if (self::isJalali($year)) {
            $start = CalendarDate::toGregorian($year, $month, 1);
            $nextYear = $month === 12 ? $year + 1 : $year;
            $nextMonth = $month === 12 ? 1 : $month + 1;
            $next = CalendarDate::toGregorian($nextYear, $nextMonth, 1);
            $from = Carbon::create($start['gy'], $start['gm'], $start['gd'])->startOfDay();
            $to = Carbon::create($next['gy'], $next['gm'], $next['gd'])->subDay()->endOfDay();

            return [$from, $to];
        }
        $from = Carbon::create($year, $month, 1)->startOfDay();

        return [$from, $from->copy()->endOfMonth()];
    }

    public static function daysInPeriod(int $year, int $month): int
    {
        [$from, $to] = self::gregorianRange($year, $month);

        return (int) $from->diffInDays($to->copy()->startOfDay()) + 1;
    }

    /** Jalali YYYYMMDD (e.g. 14050115) or empty string. */
    public static function jalaliDate8(CarbonInterface|string|null $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }
        $c = $date instanceof CarbonInterface ? $date : Carbon::parse($date);
        $j = CalendarDate::toJalali((int) $c->year, (int) $c->month, (int) $c->day);

        return sprintf('%04d%02d%02d', $j['jy'], $j['jm'], $j['jd']);
    }

    /** Convert Persian/Arabic digits to ASCII. */
    public static function asciiDigits(string $value): string
    {
        return strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }
}

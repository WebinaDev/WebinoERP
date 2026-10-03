<?php

namespace App\Support;

use Carbon\CarbonInterface;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\Request;

/**
 * Store instants as UTC ISO. Display Jalali in Asia/Tehran for fa, Gregorian UTC for en.
 * Date-only values (YYYY-MM-DD) are calendar dates and are not shifted by timezone.
 */
class CalendarDate
{
    public const TEHRAN = 'Asia/Tehran';

    /** @var array<int, int> */
    private const JALALI_BREAKS = [-61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394, 2456, 3178];

    public static function localeFromRequest(?Request $request = null): string
    {
        $request ??= request();
        $query = strtolower((string) $request->query('locale', ''));
        if (in_array($query, ['fa', 'en'], true)) {
            return $query;
        }
        $header = strtolower((string) $request->header('Accept-Language', ''));

        return str_starts_with($header, 'fa') ? 'fa' : 'en';
    }

    public static function format(CarbonInterface|string|null $value, string $locale = 'fa', bool $withTime = false): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $locale = str_starts_with(strtolower($locale), 'fa') ? 'fa' : 'en';
        $raw = $value instanceof CarbonInterface ? $value->clone()->utc()->format('Y-m-d\TH:i:s\Z') : trim((string) $value);
        $dateOnly = (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw);
        $parts = self::gregorianParts($raw, $locale === 'fa' ? self::TEHRAN : 'UTC');
        if ($parts === null) {
            return '—';
        }

        if ($locale === 'fa') {
            $j = self::toJalali($parts['y'], $parts['m'], $parts['d']);
            $text = sprintf('%04d/%02d/%02d', $j['jy'], $j['jm'], $j['jd']);
            if ($withTime && ! $dateOnly) {
                $text .= sprintf(' %02d:%02d', $parts['hh'], $parts['mm']);
            }

            return self::toPersianDigits($text);
        }

        $text = sprintf('%04d-%02d-%02d', $parts['y'], $parts['m'], $parts['d']);
        if ($withTime && ! $dateOnly) {
            $text .= sprintf(' %02d:%02d', $parts['hh'], $parts['mm']);
        }

        return $text;
    }

    /**
     * @return array{y:int,m:int,d:int,hh:int,mm:int}|null
     */
    public static function gregorianParts(string $iso, string $timeZone): ?array
    {
        $iso = trim($iso);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $iso, $m)) {
            return ['y' => (int) $m[1], 'm' => (int) $m[2], 'd' => (int) $m[3], 'hh' => 0, 'mm' => 0];
        }

        try {
            $dt = new DateTimeImmutable($iso);
        } catch (\Exception) {
            return null;
        }

        $local = $dt->setTimezone(new DateTimeZone($timeZone));

        return [
            'y' => (int) $local->format('Y'),
            'm' => (int) $local->format('n'),
            'd' => (int) $local->format('j'),
            'hh' => (int) $local->format('G'),
            'mm' => (int) $local->format('i'),
        ];
    }

    /**
     * @return array{jy:int,jm:int,jd:int}
     */
    public static function toJalali(int $gy, int $gm, int $gd): array
    {
        return self::d2j(self::g2d($gy, $gm, $gd));
    }

    /**
     * @return array{gy:int,gm:int,gd:int}
     */
    public static function toGregorian(int $jy, int $jm, int $jd): array
    {
        return self::d2g(self::j2d($jy, $jm, $jd));
    }

    public static function toPersianDigits(string $value): string
    {
        return strtr($value, [
            '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
            '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
        ]);
    }

    private static function div(int $a, int $b): int
    {
        return intdiv($a, $b);
    }

    private static function mod(int $a, int $b): int
    {
        return $a - intdiv($a, $b) * $b;
    }

    private static function g2d(int $gy, int $gm, int $gd): int
    {
        $d = self::div(($gy + self::div($gm - 8, 6) + 100100) * 1461, 4)
            + self::div(153 * self::mod($gm + 9, 12) + 2, 5)
            + $gd - 34840408;
        $d = $d - self::div(self::div($gy + 100100 + self::div($gm - 8, 6), 100) * 3, 4) + 752;

        return $d;
    }

    /**
     * @return array{gy:int,gm:int,gd:int}
     */
    private static function d2g(int $jdn): array
    {
        $j = 4 * $jdn + 139361631;
        $j = $j + self::div(self::div(4 * $jdn + 183187720, 146097) * 3, 4) * 4 - 3908;
        $i = self::div(self::mod($j, 1461), 4) * 5 + 308;
        $gd = self::div(self::mod($i, 153), 5) + 1;
        $gm = self::mod(self::div($i, 153), 12) + 1;
        $gy = self::div($j, 1461) - 100100 + self::div(8 - $gm, 6);

        return ['gy' => $gy, 'gm' => $gm, 'gd' => $gd];
    }

    /**
     * @return array{leap:int,gy:int,march:int}
     */
    private static function jalaliCalendar(int $jy): array
    {
        $breaks = self::JALALI_BREAKS;
        $bl = count($breaks);
        $gy = $jy + 621;
        $leapJ = -14;
        $jp = $breaks[0];
        $jump = 0;
        $n = 0;

        if ($jy < $jp || $jy >= $breaks[$bl - 1]) {
            throw new \InvalidArgumentException('Invalid Jalali year '.$jy);
        }

        for ($i = 1; $i < $bl; $i++) {
            $jm = $breaks[$i];
            $jump = $jm - $jp;
            if ($jy < $jm) {
                break;
            }
            $leapJ = $leapJ + self::div($jump, 33) * 8 + self::div(self::mod($jump, 33), 4);
            $jp = $jm;
        }

        $n = $jy - $jp;
        $leapJ = $leapJ + self::div($n, 33) * 8 + self::div(self::mod($n, 33) + 3, 4);
        if (self::mod($jump, 33) === 4 && $jump - $n === 4) {
            $leapJ += 1;
        }

        $leapG = self::div($gy, 4) - self::div((self::div($gy, 100) + 1) * 3, 4) - 150;
        $march = 20 + $leapJ - $leapG;

        if ($jump - $n < 6) {
            $n = $n - $jump + self::div($jump + 4, 33) * 33;
        }

        $leap = self::mod(self::mod($n + 1, 33) - 1, 4);
        if ($leap === -1) {
            $leap = 4;
        }

        return ['leap' => $leap, 'gy' => $gy, 'march' => $march];
    }

    private static function j2d(int $jy, int $jm, int $jd): int
    {
        $r = self::jalaliCalendar($jy);

        return self::g2d($r['gy'], 3, $r['march']) + ($jm - 1) * 31 - self::div($jm, 7) * ($jm - 7) + $jd - 1;
    }

    /**
     * @return array{jy:int,jm:int,jd:int}
     */
    private static function d2j(int $jdn): array
    {
        $gy = self::d2g($jdn)['gy'];
        $jy = $gy - 621;
        $r = self::jalaliCalendar($jy);
        $jdn1f = self::g2d($gy, 3, $r['march']);
        $k = $jdn - $jdn1f;

        if ($k >= 0) {
            if ($k <= 185) {
                $jm = 1 + self::div($k, 31);
                $jd = self::mod($k, 31) + 1;

                return ['jy' => $jy, 'jm' => $jm, 'jd' => $jd];
            }
            $k -= 186;
        } else {
            $jy -= 1;
            $k += 179;
            if ($r['leap'] === 1) {
                $k += 1;
            }
        }

        $jm = 7 + self::div($k, 30);
        $jd = self::mod($k, 30) + 1;

        return ['jy' => $jy, 'jm' => $jm, 'jd' => $jd];
    }
}

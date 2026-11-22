<?php

namespace Modules\Hrm\Services;

use Carbon\Carbon;
use Modules\Hrm\Entities\HrmAttendanceRecord;
use Modules\Hrm\Entities\HrmEmployee;

class HrmAttendanceWorkRuleEngine
{
    /**
     * Official statutory Iranian holidays (شمسی / قمری و ملی مصوب تقویم رسمی کشور)
     * Stored in MM-DD format (Jalali) or standard holiday dates
     */
    public const OFFICIAL_HOLIDAYS_JALALI = [
        '01-01', '01-02', '01-03', '01-04', '01-12', '01-13', // عید نوروز و روز طبیعت
        '02-12', // شهادت امام جعفر صادق
        '03-14', '03-15', // رحلت امام خمینی و قیام ۱۵ خرداد
        '03-27', // عید سعید قربان
        '04-05', // عید سعید غدیر خم
        '04-25', '04-26', // تاسوعا و عاشورای حسینی
        '06-04', // اربعین حسینی
        '06-12', '06-14', // رحلت پیامبر اکرم، شهادت امام حسن مجتبی و شهادت امام رضا
        '06-22', // شهادت امام حسن عسکری
        '06-31', // میلاد رسول اکرم و امام جعفر صادق
        '09-15', // شهادت حضرت فاطمه زهرا
        '10-24', // ولادت امام علی و روز پدر
        '11-08', // مبعث رسول اکرم
        '11-22', // پیروزی انقلاب اسلامی
        '11-26', // ولادت حضرت قائم و نیمه شعبان
        '12-21', // شهادت حضرت علی
        '12-29', // روز ملی شدن صنعت نفت
    ];

    /**
     * Check if a given date is a Friday or official Iranian holiday.
     */
    public function isHoliday(Carbon $date): bool
    {
        if ($date->isFriday()) {
            return true;
        }

        // Check Jalali month-day
        [$jy, $jm, $jd] = \Modules\Hrm\Support\HrmPeriod::toJalaliPeriod($date->year, $date->month);
        $jDay = (int) $date->format('d'); // approximate
        $formatted = sprintf('%02d-%02d', $jm, min(31, $jDay));

        return in_array($formatted, self::OFFICIAL_HOLIDAYS_JALALI, true);
    }

    /**
     * Comprehensive evaluation of night shift, holiday work, and rotation:
     * - Night hours: precisely spans between 22:00 and 06:00 (ماده ۵۸ قانون کار - ۳۵٪ فوق‌العاده)
     * - Holiday hours: Friday or calendar holidays (ماده ۶۲ قانون کار - ۴۰٪ فوق‌العاده)
     * - Standard day hours: 7.33 hours (۴۴ ساعت در هفته تقسیم بر ۶ روز)
     *
     * @return array{
     *   worked_hours: float,
     *   night_hours: float,
     *   holiday_hours: float,
     *   overtime_hours: float,
     *   undertime_hours: float,
     *   shift_allowance_percent: float
     * }
     */
    public function evaluateMonthlyAttendanceFactors(HrmEmployee $employee, int $year, int $month): array
    {
        $records = HrmAttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [
                Carbon::create($year, $month, 1)->startOfMonth()->toDateString(),
                Carbon::create($year, $month, 1)->endOfMonth()->toDateString(),
            ])
            ->get();

        $workedHours = 0.0;
        $nightHours = 0.0;
        $holidayHours = 0.0;
        $overtimeHours = 0.0;
        $undertimeHours = 0.0;

        foreach ($records as $rec) {
            $hours = (float) ($rec->worked_hours ?: 0);
            $workedHours += $hours;

            $date = Carbon::parse($rec->date);
            $isHol = $this->isHoliday($date);

            if ($isHol) {
                $holidayHours += $hours;
            }

            // Accurate Night Shift calculation (22:00 to 06:00)
            if ($rec->clock_in && $rec->clock_out) {
                $in = Carbon::parse("{$rec->date} {$rec->clock_in}");
                $out = Carbon::parse("{$rec->date} {$rec->clock_out}");
                if ($out->lessThan($in)) {
                    $out->addDay();
                }

                // Check overlap with night window
                $nightStart = Carbon::parse("{$rec->date} 22:00:00");
                $nightEnd = Carbon::parse("{$rec->date} 06:00:00")->addDay();

                if ($out->greaterThan($nightStart)) {
                    $overlapStart = $in->greaterThan($nightStart) ? $in : $nightStart;
                    $overlapEnd = $out->lessThan($nightEnd) ? $out : $nightEnd;
                    if ($overlapEnd->greaterThan($overlapStart)) {
                        $nightHours += round($overlapEnd->diffInMinutes($overlapStart) / 60, 2);
                    }
                }
            }

            $standardDay = 7.33;
            if ($hours > $standardDay) {
                $overtimeHours += ($hours - $standardDay);
            } elseif ($hours < $standardDay && $hours > 0) {
                $undertimeHours += ($standardDay - $hours);
            }
        }

        $shiftType = $employee->shift_pattern ?? 'standard';
        $shiftPercent = match ($shiftType) {
            'morning_evening' => 10.0,
            'morning_evening_night' => 15.0,
            'morning_night', 'evening_night' => 22.5,
            default => 0.0,
        };

        return [
            'worked_hours' => round($workedHours, 2),
            'night_hours' => round($nightHours, 2),
            'holiday_hours' => round($holidayHours, 2),
            'overtime_hours' => round($overtimeHours, 2),
            'undertime_hours' => round($undertimeHours, 2),
            'shift_allowance_percent' => $shiftPercent,
        ];
    }
}

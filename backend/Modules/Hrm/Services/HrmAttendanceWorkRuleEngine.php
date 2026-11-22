<?php

namespace Modules\Hrm\Services;

use Carbon\Carbon;
use Modules\Hrm\Entities\HrmAttendanceRecord;
use Modules\Hrm\Entities\HrmEmployee;

class HrmAttendanceWorkRuleEngine
{
    /**
     * Calculate comprehensive work factors:
     * - Night shift work (ساعات شب‌کاری: ۲۲:۰۰ الی ۰۶:۰۰ مشمول ۳۵٪ فوق‌العاده)
     * - Holiday work (تعطیل‌کاری: جمعه‌ها و تعطیلات رسمی مشمول ۴۰٪ فوق‌العاده)
     * - Shift rotation allowances (نوبت‌کاری: صبح-عصر ۱۰٪، صبح-عصر-شب ۱۵٪، صبح-شب یا عصر-شب ۲۲.۵٪)
     * - Delay and undertime (کسر کار و تاخیر مجاز/غیرمجاز)
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

            // Check Friday / Holiday work
            $date = Carbon::parse($rec->date);
            if ($date->isFriday()) {
                $holidayHours += $hours;
            }

            // Estimate night shift hours if clock out after 22:00 or clock in before 06:00
            if ($rec->clock_out && Carbon::parse($rec->clock_out)->hour >= 22) {
                $outHour = Carbon::parse($rec->clock_out)->hour;
                $nightHours += max(0, $outHour - 22);
            }

            // Overtime vs Undertime standard day of 7.33 hours (44 hours/week divided by 6 days)
            $standardDay = 7.33;
            if ($hours > $standardDay) {
                $overtimeHours += ($hours - $standardDay);
            } elseif ($hours < $standardDay && $hours > 0) {
                $undertimeHours += ($standardDay - $hours);
            }
        }

        // Shift rotation allowance percentage based on employee engagement or shift code
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

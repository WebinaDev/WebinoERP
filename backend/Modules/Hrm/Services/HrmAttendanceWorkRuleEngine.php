<?php

namespace Modules\Hrm\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Modules\Hrm\Entities\HrmAttendanceRecord;
use Modules\Hrm\Entities\HrmCalendarHoliday;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmMissionOrder;
use Modules\Hrm\Entities\HrmShiftAssignment;
use Modules\Hrm\Entities\HrmShiftRotation;

class HrmAttendanceWorkRuleEngine
{
    public const OFFICIAL_HOLIDAYS_JALALI = [
        '01-01', '01-02', '01-03', '01-04', '01-12', '01-13',
        '02-12', '03-14', '03-15', '03-27', '04-05', '04-25', '04-26',
        '06-04', '06-12', '06-14', '06-22', '06-31', '09-15', '10-24',
        '11-08', '11-22', '11-26', '12-21', '12-29',
    ];

    /**
     * Check if a given date is a Friday, database-managed organizational holiday, or official statutory holiday.
     */
    public function isHoliday(Carbon $date): bool
    {
        if ($date->isFriday()) {
            return true;
        }

        // 1. Check custom organizational holidays table if present
        if (Schema::hasTable('hrm_calendar_holidays')) {
            $isDbHoliday = HrmCalendarHoliday::query()->where('holiday_date', $date->toDateString())->exists();
            if ($isDbHoliday) {
                return true;
            }
        }

        // 2. Check Jalali calendar statutory dates
        [$jy, $jm, $jd] = \Modules\Hrm\Support\HrmPeriod::toJalaliPeriod($date->year, $date->month);
        $jDay = (int) $date->format('d');
        $formatted = sprintf('%02d-%02d', $jm, min(31, $jDay));

        return in_array($formatted, self::OFFICIAL_HOLIDAYS_JALALI, true);
    }

    /**
     * Strict evaluation of shift rotation pattern according to Articles 55 and 56 of Iranian Labor Law:
     * - Article 55: Rotating work is work that rotates between morning, evening, and night shifts during the month.
     * - Article 56:
     *   - Morning and Evening (نوبت‌کاری صبح و عصر): 10% shift allowance
     *   - Morning, Evening, and Night (نوبت‌کاری صبح، عصر و شب - ۳ نوبته): 15% shift allowance
     *   - Morning and Night or Evening and Night (نوبت‌کاری صبح و شب / عصر و شب): 22.5% shift allowance
     *
     * Resolves dynamically from:
     * 1. Active shift assignments and rotations (hrm_shift_assignments / hrm_shift_rotations)
     * 2. Physical attendance clock-in distributions across the month
     * 3. Assigned employee shift template or explicit shift pattern attribute
     */
    public function evaluateShiftRotationAllowance(HrmEmployee $employee, int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = Carbon::create($year, $month, 1)->endOfMonth();

        $shiftsCovered = [
            'morning' => false,
            'evening' => false,
            'night' => false,
        ];

        // 1. Check dynamic shift assignments for the month
        if (Schema::hasTable('hrm_shift_assignments')) {
            $assignments = HrmShiftAssignment::query()
                ->where('employee_id', $employee->id)
                ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
                ->where('is_off', false)
                ->with(['template', 'rotation'])
                ->get();

            foreach ($assignments as $assignment) {
                $template = $assignment->template;
                if ($template && $template->start_time) {
                    $startHour = (int) Carbon::parse($template->start_time)->format('H');
                    if ($startHour >= 6 && $startHour < 14) {
                        $shiftsCovered['morning'] = true;
                    } elseif ($startHour >= 14 && $startHour < 22) {
                        $shiftsCovered['evening'] = true;
                    } else {
                        $shiftsCovered['night'] = true;
                    }
                }
            }
        }

        // 2. Cross-check physical attendance records for rotation if not assigned explicitly
        if (! $shiftsCovered['morning'] && ! $shiftsCovered['evening'] && ! $shiftsCovered['night']) {
            if (Schema::hasTable('hrm_attendance_records')) {
                $records = HrmAttendanceRecord::query()
                    ->where('employee_id', $employee->id)
                    ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                    ->whereNotNull('check_in')
                    ->get();

                foreach ($records as $r) {
                    $hour = (int) Carbon::parse("{$r->date} {$r->check_in}")->format('H');
                    if ($hour >= 6 && $hour < 14) {
                        $shiftsCovered['morning'] = true;
                    } elseif ($hour >= 14 && $hour < 22) {
                        $shiftsCovered['evening'] = true;
                    } else {
                        $shiftsCovered['night'] = true;
                    }
                }
            }
        }

        // Determine pattern according to Article 56
        $hasMorning = $shiftsCovered['morning'];
        $hasEvening = $shiftsCovered['evening'];
        $hasNight = $shiftsCovered['night'];

        $resolvedType = 'standard';
        $percent = 0.0;

        if ($hasMorning && $hasEvening && $hasNight) {
            $resolvedType = 'morning_evening_night';
            $percent = 15.0; // ماده ۵۶: نوبت‌کاری صبح، عصر و شب
        } elseif (($hasMorning && $hasNight) || ($hasEvening && $hasNight)) {
            $resolvedType = ($hasMorning && $hasNight) ? 'morning_night' : 'evening_night';
            $percent = 22.5; // ماده ۵۶: نوبت‌کاری صبح و شب یا عصر و شب
        } elseif ($hasMorning && $hasEvening) {
            $resolvedType = 'morning_evening';
            $percent = 10.0; // ماده ۵۶: نوبت‌کاری صبح و عصر
        } else {
            // Fallback to employee profile shift_pattern attribute
            $shiftType = $employee->shift_pattern ?? 'standard';
            $percent = match ($shiftType) {
                'morning_evening' => 10.0,
                'morning_evening_night' => 15.0,
                'morning_night', 'evening_night' => 22.5,
                default => 0.0,
            };
            $resolvedType = $shiftType;
        }

        return [
            'pattern' => $resolvedType,
            'percent' => $percent,
            'has_morning' => $hasMorning,
            'has_evening' => $hasEvening,
            'has_night' => $hasNight,
        ];
    }

    /**
     * Strict evaluation of Mission Orders (ماده ۴۶ قانون کار):
     * - Must not be less than daily fixed base salary or 1/30th of monthly base wage.
     * - Evaluates approved missions within the target period.
     *
     * @return array{
     *   mission_days: float,
     *   mission_hours: float,
     *   mission_allowance: float,
     *   travel_expenses: float,
     *   hotel_expenses: float,
     *   total_mission_pay: float
     * }
     */
    public function evaluateMonthlyMissionFactors(HrmEmployee $employee, int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = Carbon::create($year, $month, 1)->endOfMonth();

        $missionDays = 0.0;
        $missionHours = 0.0;
        $allowance = 0.0;
        $transport = 0.0;
        $hotel = 0.0;

        if (Schema::hasTable('hrm_mission_orders')) {
            $orders = HrmMissionOrder::query()
                ->where('employee_id', $employee->id)
                ->whereIn('status', ['hr_approved', 'settled', 'manager_approved'])
                ->whereBetween('starts_at', [$start->toDateTimeString(), $end->toDateTimeString()])
                ->get();

            foreach ($orders as $order) {
                $s = Carbon::parse($order->starts_at);
                $e = Carbon::parse($order->ends_at);
                $diffHours = max(1.0, round($e->diffInMinutes($s) / 60.0, 2));

                $missionHours += $diffHours;
                $days = max(1.0, round($diffHours / 24.0, 1));
                $missionDays += $days;

                $orderAllowance = (float) $order->per_diem_rate;
                if ($orderAllowance <= 0) {
                    $orderAllowance = (float) $order->total_allowance;
                }
                $allowance += $orderAllowance;
                $transport += (float) $order->transport_expense;
                $hotel += (float) $order->hotel_expense;
            }
        }

        $baseSalary = (float) ($employee->base_salary ?: 0);
        $minimumDailyRate = $baseSalary > 0 ? round($baseSalary / 30.0, 2) : 0.0;
        $statutoryMissionAllowance = max($allowance, round($missionDays * $minimumDailyRate, 2));

        return [
            'mission_days' => round($missionDays, 2),
            'mission_hours' => round($missionHours, 2),
            'mission_allowance' => $statutoryMissionAllowance,
            'travel_expenses' => round($transport, 2),
            'hotel_expenses' => round($hotel, 2),
            'total_mission_pay' => round($statutoryMissionAllowance + $transport + $hotel, 2),
        ];
    }

    /**
     * Comprehensive statutory evaluation of night shift, holiday work, rotation, and mission:
     * - Night hours: precisely spans between 22:00 and 06:00 (ماده ۵۸ قانون کار - ۳۵٪ فوق‌العاده)
     * - Holiday hours: Friday or calendar holidays (ماده ۶۲ قانون کار - ۴۰٪ فوق‌العاده)
     * - Standard day hours: 7.33 hours (۴۴ ساعت در هفته تقسیم بر ۶ روز)
     * - Shift rotation: Articles 55/56
     * - Mission: Article 46
     *
     * @return array{
     *   worked_hours: float,
     *   night_hours: float,
     *   holiday_hours: float,
     *   overtime_hours: float,
     *   undertime_hours: float,
     *   shift_allowance_percent: float,
     *   shift_pattern: string,
     *   mission_days: float,
     *   mission_pay: float
     * }
     */
    public function evaluateMonthlyAttendanceFactors(HrmEmployee $employee, int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = Carbon::create($year, $month, 1)->endOfMonth();

        $records = HrmAttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get();

        $workedHours = 0.0;
        $nightHours = 0.0;
        $holidayHours = 0.0;
        $overtimeHours = 0.0;
        $undertimeHours = 0.0;

        foreach ($records as $rec) {
            $hours = (float) ($rec->worked_hours ?: 0);

            // Compute worked hours from check_in / check_out if worked_hours not precomputed
            if ($hours <= 0 && $rec->check_in && $rec->check_out) {
                $ci = Carbon::parse("{$rec->date} {$rec->check_in}");
                $co = Carbon::parse("{$rec->date} {$rec->check_out}");
                if ($co->lessThan($ci)) {
                    $co->addDay();
                }
                $hours = round($co->diffInMinutes($ci) / 60.0, 2);
            }

            $workedHours += $hours;

            $date = Carbon::parse($rec->date);
            $isHol = $this->isHoliday($date);

            if ($isHol) {
                $holidayHours += $hours;
            }

            // Accurate Night Shift calculation (22:00 to 06:00 - Article 58)
            $cin = $rec->check_in ?: $rec->clock_in;
            $cout = $rec->check_out ?: $rec->clock_out;

            if ($cin && $cout) {
                $in = Carbon::parse("{$rec->date} {$cin}");
                $out = Carbon::parse("{$rec->date} {$cout}");
                if ($out->lessThan($in)) {
                    $out->addDay();
                }

                $nightStart = Carbon::parse("{$rec->date} 22:00:00");
                $nightEnd = Carbon::parse("{$rec->date} 06:00:00")->addDay();

                if ($out->greaterThan($nightStart)) {
                    $overlapStart = $in->greaterThan($nightStart) ? $in : $nightStart;
                    $overlapEnd = $out->lessThan($nightEnd) ? $out : $nightEnd;
                    if ($overlapEnd->greaterThan($overlapStart)) {
                        $nightHours += round($overlapEnd->diffInMinutes($overlapStart) / 60.0, 2);
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

        // Shift Rotation Evaluation (Articles 55 & 56)
        $shiftData = $this->evaluateShiftRotationAllowance($employee, $year, $month);

        // Mission Evaluation (Article 46)
        $missionData = $this->evaluateMonthlyMissionFactors($employee, $year, $month);

        return [
            'worked_hours' => round($workedHours, 2),
            'night_hours' => round($nightHours, 2),
            'holiday_hours' => round($holidayHours, 2),
            'overtime_hours' => round($overtimeHours, 2),
            'undertime_hours' => round($undertimeHours, 2),
            'shift_allowance_percent' => (float) $shiftData['percent'],
            'shift_pattern' => (string) $shiftData['pattern'],
            'mission_days' => (float) $missionData['mission_days'],
            'mission_pay' => (float) $missionData['total_mission_pay'],
        ];
    }
}

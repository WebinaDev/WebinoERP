<?php

namespace Modules\Hrm\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Hrm\Entities\HrmCalendarFeed;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmLeaveRequest;
use Modules\Hrm\Entities\HrmShiftAssignment;

/**
 * Per-employee iCalendar (RFC 5545) feed: approved leave (all-day) and assigned shifts (timed, Asia/Tehran).
 * Subscribed through a secret tokenized URL; regenerating the token revokes the old link.
 */
class HrmCalendarFeedService
{
    private const TZ = 'Asia/Tehran';

    /** @var array<string, array{fa: string, en: string}> */
    private const LEAVE_LABELS = [
        'annual' => ['fa' => 'مرخصی استحقاقی', 'en' => 'Annual leave'],
        'sick' => ['fa' => 'مرخصی استعلاجی', 'en' => 'Sick leave'],
        'unpaid' => ['fa' => 'مرخصی بدون حقوق', 'en' => 'Unpaid leave'],
        'hourly' => ['fa' => 'مرخصی ساعتی', 'en' => 'Hourly leave'],
        'maternity' => ['fa' => 'مرخصی زایمان', 'en' => 'Maternity leave'],
        'marriage' => ['fa' => 'مرخصی ازدواج', 'en' => 'Marriage leave'],
        'bereavement' => ['fa' => 'مرخصی فوت بستگان', 'en' => 'Bereavement leave'],
    ];

    public function feedFor(HrmEmployee $employee): HrmCalendarFeed
    {
        return HrmCalendarFeed::query()->firstOrCreate(
            ['employee_id' => $employee->id],
            ['token' => Str::random(48)]
        );
    }

    public function regenerate(HrmEmployee $employee): HrmCalendarFeed
    {
        $feed = $this->feedFor($employee);
        $feed->update(['token' => Str::random(48)]);

        return $feed->fresh();
    }

    /**
     * @return array<string, string>
     */
    public function links(HrmCalendarFeed $feed): array
    {
        $https = url('/api/v1/hrm/calendar/feeds/'.$feed->token.'.ics');
        $webcal = preg_replace('#^https?://#', 'webcal://', $https) ?? $https;

        return [
            'token' => $feed->token,
            'feed_url' => $https,
            'webcal_url' => $webcal,
            'google_url' => 'https://calendar.google.com/calendar/render?cid='.rawurlencode($webcal),
            'outlook_url' => 'https://outlook.live.com/calendar/0/addfromweb?url='.rawurlencode($https).'&name='.rawurlencode('Webino HR'),
            'created_at' => optional($feed->updated_at)->toIso8601String() ?? '',
        ];
    }

    public function ics(HrmEmployee $employee, string $lang = 'fa'): string
    {
        $lang = $lang === 'en' ? 'en' : 'fa';
        $name = trim($employee->first_name.' '.$employee->last_name);
        $stamp = now('UTC')->format('Ymd\THis\Z');
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//WebinoERP//HRM//'.strtoupper($lang),
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$this->esc(($lang === 'fa' ? 'منابع انسانی — ' : 'HR — ').$name),
            'X-WR-TIMEZONE:'.self::TZ,
            'REFRESH-INTERVAL;VALUE=DURATION:PT6H',
            'X-PUBLISHED-TTL:PT6H',
            'BEGIN:VTIMEZONE',
            'TZID:'.self::TZ,
            'BEGIN:STANDARD',
            'DTSTART:19700101T000000',
            'TZOFFSETFROM:+0330',
            'TZOFFSETTO:+0330',
            'TZNAME:+0330',
            'END:STANDARD',
            'END:VTIMEZONE',
        ];

        $leaves = HrmLeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where(function ($q) {
                $since = now()->subYear()->toDateString();
                $q->where('end_date', '>=', $since)->orWhere(fn ($w) => $w->whereNull('end_date')->where('start_date', '>=', $since));
            })
            ->orderBy('start_date')
            ->limit(500)
            ->get();
        foreach ($leaves as $leave) {
            if (! $leave->start_date) {
                continue;
            }
            $end = ($leave->end_date ?: $leave->start_date)->copy()->addDay();
            $label = self::LEAVE_LABELS[(string) $leave->type][$lang] ?? (($lang === 'fa' ? 'مرخصی ' : 'Leave ').$leave->type);
            array_push($lines,
                'BEGIN:VEVENT',
                'UID:leave-'.$leave->id.'@webinoerp',
                'DTSTAMP:'.$stamp,
                'DTSTART;VALUE=DATE:'.$leave->start_date->format('Ymd'),
                'DTEND;VALUE=DATE:'.$end->format('Ymd'),
                'SUMMARY:'.$this->esc($label),
                'TRANSP:OPAQUE',
                'CATEGORIES:'.($lang === 'fa' ? 'مرخصی' : 'Leave'),
                'END:VEVENT'
            );
        }

        if (Schema::hasTable('hrm_shift_assignments')) {
            $shifts = HrmShiftAssignment::query()
                ->with('template')
                ->where('employee_id', $employee->id)
                ->where('work_date', '>=', now()->subMonths(3)->toDateString())
                ->orderBy('work_date')
                ->limit(500)
                ->get();
            foreach ($shifts as $shift) {
                if (! $shift->work_date) {
                    continue;
                }
                $day = Carbon::parse($shift->work_date);
                $template = $shift->template;
                if ($shift->is_off || ! $template) {
                    array_push($lines,
                        'BEGIN:VEVENT',
                        'UID:shift-'.$shift->id.'@webinoerp',
                        'DTSTAMP:'.$stamp,
                        'DTSTART;VALUE=DATE:'.$day->format('Ymd'),
                        'DTEND;VALUE=DATE:'.$day->copy()->addDay()->format('Ymd'),
                        'SUMMARY:'.$this->esc($lang === 'fa' ? 'روز استراحت' : 'Day off'),
                        'TRANSP:TRANSPARENT',
                        'END:VEVENT'
                    );
                    continue;
                }
                [$start, $end] = $this->shiftTimes($day, (string) $template->start_time, (string) $template->end_time);
                array_push($lines,
                    'BEGIN:VEVENT',
                    'UID:shift-'.$shift->id.'@webinoerp',
                    'DTSTAMP:'.$stamp,
                    'DTSTART;TZID='.self::TZ.':'.$start->format('Ymd\THis'),
                    'DTEND;TZID='.self::TZ.':'.$end->format('Ymd\THis'),
                    'SUMMARY:'.$this->esc(($lang === 'fa' ? 'شیفت ' : 'Shift ').$template->name),
                    'CATEGORIES:'.($lang === 'fa' ? 'شیفت' : 'Shift'),
                    'END:VEVENT'
                );
            }
        }
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(fn ($l) => $this->fold($l), $lines))."\r\n";
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function shiftTimes(Carbon $day, string $startTime, string $endTime): array
    {
        $parse = function (string $t, string $fallback) use ($day) {
            $t = preg_match('/^\d{1,2}:\d{2}/', $t) ? $t : $fallback;
            [$h, $m] = array_map('intval', explode(':', $t));

            return $day->copy()->setTime($h, $m);
        };
        $start = $parse($startTime, '08:00');
        $end = $parse($endTime, '16:00');
        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay(); // night shift
        }

        return [$start, $end];
    }

    private function esc(string $value): string
    {
        return str_replace(["\\", "\r\n", "\n", "\r", ',', ';'], ['\\\\', '\\n', '\\n', '', '\\,', '\\;'], $value);
    }

    /** RFC 5545 line folding at 75 octets without splitting UTF-8 characters. */
    private function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }
        $out = '';
        $current = '';
        $limit = 75;
        foreach (mb_str_split($line) as $char) {
            if (strlen($current) + strlen($char) > $limit) {
                $out .= $current."\r\n ";
                $current = '';
                $limit = 74;
            }
            $current .= $char;
        }

        return $out.$current;
    }
}

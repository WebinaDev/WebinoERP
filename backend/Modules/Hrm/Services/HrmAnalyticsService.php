<?php

namespace Modules\Hrm\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Hrm\Entities\HrmAttendanceRecord;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmPayrollItem;

class HrmAnalyticsService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(Carbon $from, Carbon $to): array
    {
        $active = HrmEmployee::query()->where('status', 'active')->count();
        $leavers = HrmEmployee::query()
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('updated_at', [$from, $to])
                    ->whereIn('status', ['inactive', 'terminated', 'resigned', 'left']);
            })
            ->count();
        $trashed = HrmEmployee::onlyTrashed()
            ->whereBetween('deleted_at', [$from, $to])
            ->count();
        $leavers += $trashed;
        $hires = HrmEmployee::query()
            ->whereBetween('hire_date', [$from->toDateString(), $to->toDateString()])
            ->count();
        $average = max(1, $active + $leavers);
        $rate = round(($leavers / $average) * 100, 2);

        $items = HrmPayrollItem::query()
            ->whereHas('payrollRun', function ($q) use ($from, $to) {
                $q->whereIn('status', ['approved', 'paid', 'calculated'])
                    ->whereBetween('updated_at', [$from, $to]);
            })
            ->get();
        $gross = 0.0;
        $net = 0.0;
        $employer = 0.0;
        foreach ($items as $item) {
            $gross += (float) $item->gross;
            $net += (float) $item->net;
            $employer += (float) ($item->breakdown['employer_insurance'] ?? 0);
        }

        $heatmap = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        if ($cursor->diffInDays($end) > 120) {
            $cursor = $end->copy()->subDays(120);
        }
        $records = HrmAttendanceRecord::query()
            ->whereBetween('date', [$cursor->toDateString(), $end->toDateString()])
            ->get(['date', 'status']);
        $byDate = [];
        foreach ($records as $rec) {
            $key = optional($rec->date)->toDateString();
            if (! $key) {
                continue;
            }
            $byDate[$key] ??= ['present' => 0, 'absent' => 0, 'leave' => 0, 'other' => 0];
            $status = (string) $rec->status;
            if (in_array($status, ['present', 'checked_in', 'worked'], true)) {
                $byDate[$key]['present']++;
            } elseif (in_array($status, ['absent'], true)) {
                $byDate[$key]['absent']++;
            } elseif (in_array($status, ['leave', 'on_leave'], true)) {
                $byDate[$key]['leave']++;
            } else {
                $byDate[$key]['other']++;
            }
        }
        $weekdays = array_fill(0, 7, ['present' => 0, 'absent' => 0, 'leave' => 0]);
        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $cell = $byDate[$key] ?? ['present' => 0, 'absent' => 0, 'leave' => 0, 'other' => 0];
            $heatmap[] = ['date' => $key, 'weekday' => $cursor->dayOfWeek] + $cell;
            $weekdays[$cursor->dayOfWeek]['present'] += $cell['present'];
            $weekdays[$cursor->dayOfWeek]['absent'] += $cell['absent'];
            $weekdays[$cursor->dayOfWeek]['leave'] += $cell['leave'];
            $cursor->addDay();
        }

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'turnover' => [
                'rate' => $rate,
                'leavers' => $leavers,
                'hires' => $hires,
                'headcount' => $active,
                'average_headcount' => $average,
            ],
            'labor_cost' => [
                'gross' => round($gross, 2),
                'net' => round($net, 2),
                'employer_insurance' => round($employer, 2),
                'payslips' => $items->count(),
            ],
            'attendance_heatmap' => $heatmap,
            'attendance_by_weekday' => $weekdays,
        ];
    }
}

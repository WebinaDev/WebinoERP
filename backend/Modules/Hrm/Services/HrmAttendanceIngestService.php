<?php

namespace Modules\Hrm\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Modules\Hrm\Entities\HrmAttendanceDevice;
use Modules\Hrm\Entities\HrmAttendanceDeviceUser;
use Modules\Hrm\Entities\HrmAttendancePunch;
use Modules\Hrm\Entities\HrmAttendanceRecord;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmTimesheet;
use Modules\Hrm\Support\HrmPeriod;
use Modules\Hrm\Support\IranianNationalId;

class HrmAttendanceIngestService
{
    /**
     * @param  list<array<string, mixed>>  $punches
     * @return array{applied: int, unmatched: int, conflict: int, duplicate: int, invalid: int, punches: list<HrmAttendancePunch>}
     */
    public function ingest(?HrmAttendanceDevice $device, array $punches, string $source = 'device'): array
    {
        $stats = ['applied' => 0, 'unmatched' => 0, 'conflict' => 0, 'duplicate' => 0, 'invalid' => 0, 'punches' => []];
        foreach ($punches as $raw) {
            if (! is_array($raw)) {
                continue;
            }
            $p = $this->ingestOne($device, $raw, $source);
            $stats['punches'][] = $p;
            $status = (string) $p->status;
            if (isset($stats[$status])) {
                $stats[$status]++;
            }
        }

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public function ingestOne(?HrmAttendanceDevice $device, array $raw, string $source): HrmAttendancePunch
    {
        $direction = $this->direction($raw);
        $code = $this->code($raw);
        $national = IranianNationalId::normalize((string) ($raw['national_id'] ?? $raw['nationalId'] ?? ''));
        $when = $this->parseTime($raw);

        if (! $when) {
            return HrmAttendancePunch::query()->create([
                'device_id' => $device?->id,
                'raw_code' => $code !== '' ? $code : null,
                'national_id' => $national !== '' ? $national : null,
                'direction' => $direction,
                'source' => $source,
                'status' => 'invalid',
                'conflict_note' => 'unparseable_timestamp',
                'payload' => $raw,
            ]);
        }

        $employee = $this->matchEmployee($device, $code, $national);

        if (! $employee) {
            return HrmAttendancePunch::query()->create([
                'device_id' => $device?->id,
                'raw_code' => $code !== '' ? $code : null,
                'national_id' => $national !== '' ? $national : null,
                'punched_at' => $when,
                'direction' => $direction,
                'source' => $source,
                'status' => 'unmatched',
                'conflict_note' => 'no_matching_employee',
                'payload' => $raw,
            ]);
        }

        [$status, $note, $recordId] = $this->applyToAttendance($employee, $when, $direction, $device, $source);

        // Solid Bridge: Automatically synchronize attendance records with timesheets
        if ($recordId && $status === 'applied') {
            $this->syncTimesheetFromAttendanceRecord($employee, $when->toDateString());
        }

        return HrmAttendancePunch::query()->create([
            'device_id' => $device?->id,
            'employee_id' => $employee->id,
            'raw_code' => $code !== '' ? $code : null,
            'national_id' => $national !== '' ? $national : null,
            'punched_at' => $when,
            'direction' => $direction,
            'source' => $source,
            'status' => $status,
            'conflict_note' => $note,
            'attendance_record_id' => $recordId,
            'payload' => $raw,
        ]);
    }

    /**
     * HR resolves an unmatched punch: optionally remember the mapping, then apply it.
     */
    public function assign(HrmAttendancePunch $punch, HrmEmployee $employee, bool $remember = true): HrmAttendancePunch
    {
        if ($remember && $punch->raw_code) {
            HrmAttendanceDeviceUser::query()->updateOrCreate(
                ['device_id' => $punch->device_id, 'device_user_id' => $punch->raw_code],
                ['employee_id' => $employee->id]
            );
        }
        $device = $punch->device_id ? HrmAttendanceDevice::query()->find($punch->device_id) : null;
        $when = Carbon::parse($punch->punched_at);
        [$status, $note, $recordId] = $this->applyToAttendance(
            $employee,
            $when,
            (string) $punch->direction,
            $device,
            (string) $punch->source
        );
        $punch->update([
            'employee_id' => $employee->id,
            'status' => $status,
            'conflict_note' => $note,
            'attendance_record_id' => $recordId,
        ]);

        if ($recordId && $status === 'applied') {
            $this->syncTimesheetFromAttendanceRecord($employee, $when->toDateString());
        }

        return $punch->fresh(['device', 'employee']);
    }

    /**
     * Solid bridge between Attendance Records (from Device / Connector / Ingest) and Timesheets.
     * Computes worked hours, overtime, and night shift, creating/updating approved timesheets for payroll.
     */
    public function syncTimesheetFromAttendanceRecord(HrmEmployee $employee, string $date): ?HrmTimesheet
    {
        if (! Schema::hasTable('hrm_timesheets') || ! Schema::hasTable('hrm_attendance_records')) {
            return null;
        }

        $record = HrmAttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', $date)
            ->first();

        if (! $record || ! $record->check_in || ! $record->check_out) {
            return null;
        }

        $in = Carbon::parse("{$date} {$record->check_in}");
        $out = Carbon::parse("{$date} {$record->check_out}");
        if ($out->lessThan($in)) {
            $out->addDay();
        }

        $totalMinutes = max(0, $out->diffInMinutes($in));
        $hours = round($totalMinutes / 60.0, 2);

        // Determine hour_type (standard, overtime, night)
        $hourType = 'standard';
        $standardDay = 7.33; // 44h / 6 days standard

        $nightStart = Carbon::parse("{$date} 22:00:00");
        if ($out->greaterThan($nightStart)) {
            $hourType = 'night';
        } elseif ($hours > $standardDay) {
            $hourType = 'overtime';
        }

        return HrmTimesheet::query()->updateOrCreate(
            [
                'employee_id' => $employee->id,
                'work_date' => $date,
            ],
            [
                'hours' => $hours,
                'hour_type' => $hourType,
                'status' => 'approved',
                'description' => "ثبت خودکار از دستگاه حضور و غیاب ({$record->check_in} الی {$record->check_out})",
            ]
        );
    }

    /**
     * @return array{0: string, 1: ?string, 2: ?int}
     */
    private function applyToAttendance(HrmEmployee $employee, Carbon $when, string $direction, ?HrmAttendanceDevice $device, string $source): array
    {
        $hasSource = Schema::hasColumn('hrm_attendance_records', 'source');
        $date = $when->toDateString();
        $record = HrmAttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', $date)
            ->first();
        if (! $record) {
            $attrs = ['employee_id' => $employee->id, 'date' => $date, 'status' => 'present'];
            if ($hasSource) {
                $attrs['source'] = $source;
                $attrs['device_id'] = $device?->id;
            }
            $record = HrmAttendanceRecord::query()->create($attrs);
        }

        $column = $direction === 'out' ? 'check_out' : 'check_in';
        $existing = $record->{$column};
        $clock = $when->format('H:i:s');
        $payload = [];

        if ($existing === null || $existing === '') {
            $payload[$column] = $clock;
        } else {
            $existingClock = substr((string) $existing, -8);
            if (strlen($existingClock) === 5) {
                $existingClock .= ':00';
            }
            if (substr($existingClock, 0, 5) === substr($clock, 0, 5)) {
                return ['duplicate', 'same_minute', $record->id];
            }
            $recordSource = $hasSource ? (string) ($record->source ?? 'manual') : 'manual';
            if (! in_array($recordSource, ['device', 'csv'], true)) {
                return ['conflict', 'manual_record_kept', $record->id];
            }
            $earlier = $clock < $existingClock;
            if (($column === 'check_in' && $earlier) || ($column === 'check_out' && ! $earlier)) {
                $payload[$column] = $clock;
            } else {
                return ['duplicate', 'outside_first_last', $record->id];
            }
        }

        if (in_array((string) $record->status, ['', 'absent'], true)) {
            $payload['status'] = 'present';
        }
        if ($hasSource && $device && ! $record->device_id) {
            $payload['device_id'] = $device->id;
        }
        $record->update($payload);

        return ['applied', null, $record->id];
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function parseTime(array $raw): ?Carbon
    {
        $value = $raw['punched_at'] ?? $raw['datetime'] ?? $raw['time'] ?? $raw['timestamp'] ?? null;
        if (isset($raw['date']) && isset($raw['time']) && ! isset($raw['punched_at']) && ! isset($raw['datetime'])) {
            $value = trim((string) $raw['date']).' '.trim((string) $raw['time']);
        }
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        if (is_numeric($value) && (int) $value > 1000000000) {
            return Carbon::createFromTimestamp((int) $value, config('app.timezone'));
        }

        $str = trim((string) $value);
        if (preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})\s+(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?$/', $str, $m)) {
            $year = (int) $m[1];
            $month = (int) $m[2];
            $day = (int) $m[3];
            $h = (int) $m[4];
            $min = (int) $m[5];
            $sec = isset($m[6]) ? (int) $m[6] : 0;
            if ($year < 1500) {
                [$gy, $gm, $gd] = HrmPeriod::toGregorianPeriod($year, $month);

                return Carbon::create($gy, $gm, $day, $h, $min, $sec, config('app.timezone'));
            }

            return Carbon::create($year, $month, $day, $h, $min, $sec, config('app.timezone'));
        }

        try {
            return Carbon::parse($str, config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function direction(array $raw): string
    {
        $value = $raw['direction'] ?? $raw['status'] ?? $raw['state'] ?? $raw['in_out'] ?? $raw['punch'] ?? 'in';
        if (is_numeric($value)) {
            return in_array((int) $value, [1, 2, 5], true) ? 'out' : 'in';
        }
        $norm = strtolower(trim((string) $value));
        if (in_array($norm, ['out', 'exit', 'khurooj', 'khorooj', 'leaving', 'off'], true)) {
            return 'out';
        }

        return 'in';
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function code(array $raw): string
    {
        foreach (['device_user_id', 'employee_code', 'pin', 'uid', 'user_id', 'userid', 'badge', 'ac_no'] as $key) {
            if (isset($raw[$key]) && trim((string) $raw[$key]) !== '') {
                return HrmPeriod::asciiDigits(trim((string) $raw[$key]));
            }
        }

        return '';
    }

    private function matchEmployee(?HrmAttendanceDevice $device, string $code, string $national): ?HrmEmployee
    {
        if ($code !== '' && Schema::hasTable('hrm_attendance_device_users')) {
            $map = HrmAttendanceDeviceUser::query()
                ->where('device_user_id', $code)
                ->where(function ($q) use ($device) {
                    $q->whereNull('device_id');
                    if ($device) {
                        $q->orWhere('device_id', $device->id);
                    }
                })
                ->first();
            if ($map && $map->employee_id) {
                return HrmEmployee::query()->find($map->employee_id);
            }
        }

        if ($code !== '') {
            $emp = HrmEmployee::query()->where('employee_code', $code)->first();
            if ($emp) {
                return $emp;
            }
        }

        if ($national !== '' && strlen($national) === 10) {
            $emp = HrmEmployee::query()->where('national_id', $national)->first();
            if ($emp) {
                return $emp;
            }
            if (Schema::hasTable('hrm_employee_profiles')) {
                $p = \Modules\Hrm\Entities\HrmEmployeeProfile::query()->where('national_id', $national)->first();
                if ($p && $p->employee_id) {
                    return HrmEmployee::query()->find($p->employee_id);
                }
            }
        }

        return null;
    }

    public function parseCsv(string $csv): array
    {
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv) ?? $csv;
        $lines = preg_split("/\r\n|\n|\r/", trim($csv)) ?: [];
        if ($lines === [] || trim((string) $lines[0]) === '') {
            return [];
        }

        $sep = $this->separator($lines[0]);
        $header = array_map([$this, 'normHeader'], str_getcsv(array_shift($lines), $sep));

        $rows = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cols = str_getcsv($line, $sep);
            $row = [];
            foreach ($header as $idx => $key) {
                $row[$key] = $cols[$idx] ?? '';
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private function separator(string $line): string
    {
        $counts = ["\t" => substr_count($line, "\t"), ';' => substr_count($line, ';'), ',' => substr_count($line, ',')];
        arsort($counts);
        $best = array_key_first($counts);

        return $counts[$best] > 0 ? $best : ',';
    }

    private function normHeader(string $header): string
    {
        $h = mb_strtolower(trim($header));
        $h = str_replace(['.', '-', ' '], ['', '_', '_'], $h);

        return trim($h, '_');
    }
}

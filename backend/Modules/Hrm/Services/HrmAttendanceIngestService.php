<?php

namespace Modules\Hrm\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Modules\Hrm\Entities\HrmAttendanceDevice;
use Modules\Hrm\Entities\HrmAttendanceDeviceUser;
use Modules\Hrm\Entities\HrmAttendancePunch;
use Modules\Hrm\Entities\HrmAttendanceRecord;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmEmployeeProfile;
use Modules\Hrm\Support\HrmPeriod;
use Modules\Hrm\Support\IranianNationalId;

/**
 * Maps device / ZKTeco-style punches onto attendance rows.
 *
 * Matching order: device user mapping (this device, then any device) → personnel code → national ID.
 * Rows created by devices/CSV keep the earliest check-in and the latest check-out.
 * Rows typed in by HR (source manual) are never overwritten; the punch is stored as conflict.
 */
class HrmAttendanceIngestService
{
    public const STATUSES = ['applied', 'duplicate', 'conflict', 'unmatched', 'invalid'];

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
            $punch = $this->ingestOne($device, $raw, $source);
            $stats['punches'][] = $punch;
            $bucket = in_array($punch->status, self::STATUSES, true) ? $punch->status : 'applied';
            $stats[$bucket]++;
        }
        if ($device) {
            $device->forceFill(['last_seen_at' => now()])->save();
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
                'punched_at' => now(),
                'direction' => $direction,
                'source' => $source,
                'status' => 'invalid',
                'conflict_note' => 'invalid_time',
                'payload' => $raw,
            ]);
        }

        $dup = HrmAttendancePunch::query()
            ->where('punched_at', $when->format('Y-m-d H:i:s'))
            ->where('direction', $direction)
            ->where('device_id', $device?->id)
            ->where('raw_code', $code !== '' ? $code : null)
            ->when($code === '' && $national !== '', fn ($q) => $q->where('national_id', $national))
            ->first();
        if ($dup) {
            $dup->setAttribute('status', 'duplicate');

            return $dup;
        }

        $employee = $this->matchEmployee($device, $code, $national);
        $status = 'unmatched';
        $note = 'no_employee';
        $recordId = null;
        if ($employee) {
            [$status, $note, $recordId] = $this->applyToAttendance($employee, $when, $direction, $device, $source);
        }

        return HrmAttendancePunch::query()->create([
            'device_id' => $device?->id,
            'employee_id' => $employee?->id,
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
        [$status, $note, $recordId] = $this->applyToAttendance(
            $employee,
            Carbon::parse($punch->punched_at),
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

        return $punch->fresh(['device', 'employee']);
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
        $text = HrmPeriod::asciiDigits(trim((string) $value));
        $text = str_replace('/', '-', $text);
        try {
            $parsed = Carbon::parse($text);
        } catch (\Throwable) {
            return null;
        }
        if ($parsed->year < 1700) {
            // Jalali date from a local terminal (e.g. 1405-07-16 08:01)
            $g = \App\Support\CalendarDate::toGregorian($parsed->year, $parsed->month, $parsed->day);
            $parsed = Carbon::create($g['gy'], $g['gm'], $g['gd'], $parsed->hour, $parsed->minute, $parsed->second);
        }

        return $parsed;
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
        $text = mb_strtolower(trim((string) $value));
        if (in_array($text, ['out', 'o', 'checkout', 'check-out', 'check out', 'c/out', 'break out', 'overtime out', 'خروج'], true)) {
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
                ->orderByRaw('CASE WHEN device_id IS NULL THEN 1 ELSE 0 END')
                ->first();
            if ($map && $map->employee) {
                return $map->employee;
            }
        }
        if ($code !== '') {
            $byCode = HrmEmployee::query()->where('employee_code', $code)->first();
            if ($byCode) {
                return $byCode;
            }
        }
        $nid = $national !== '' ? $national : (strlen($code) === 10 ? $code : '');
        if ($nid !== '' && Schema::hasTable('hrm_employee_profiles')) {
            $profile = HrmEmployeeProfile::query()->where('national_id', $nid)->first();
            if ($profile && $profile->employee) {
                return $profile->employee;
            }
        }

        return null;
    }

    /**
     * ZKTeco / generic attendance export. Recognised headers (case-insensitive):
     * PIN, AC-No., No., User ID, employee_code, national_id, DateTime, Date + Time, Status, State, direction, Device.
     * Without headers: code, datetime, status, device. Comma, semicolon and tab separators are accepted.
     *
     * @return list<array<string, mixed>>
     */
    public function parseCsv(string $csv): array
    {
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv) ?? $csv;
        $lines = preg_split("/\r\n|\n|\r/", trim($csv)) ?: [];
        if ($lines === [] || trim((string) $lines[0]) === '') {
            return [];
        }
        $sep = $this->separator((string) $lines[0]);
        $headers = array_map(fn ($h) => $this->normHeader((string) $h), str_getcsv((string) $lines[0], $sep));
        $aliases = [
            'code' => ['pin', 'ac_no', 'no', 'user_id', 'userid', 'employee_code', 'personnel_code', 'enroll_number', 'badge', 'کد_پرسنلی', 'کد'],
            'national_id' => ['national_id', 'nationalid', 'کد_ملی'],
            'datetime' => ['datetime', 'date_time', 'punched_at', 'timestamp', 'check_time', 'checktime', 'تاریخ_و_ساعت'],
            'date' => ['date', 'تاریخ'],
            'time' => ['time', 'ساعت'],
            'direction' => ['status', 'state', 'direction', 'in_out', 'checktype', 'check_type', 'punch', 'وضعیت'],
            'device' => ['device', 'device_code', 'terminal', 'sn', 'دستگاه'],
        ];
        $index = [];
        foreach ($aliases as $field => $names) {
            foreach ($headers as $i => $h) {
                if (in_array($h, $names, true) && ! isset($index[$field])) {
                    $index[$field] = $i;
                }
            }
        }
        $hasHeader = isset($index['code']) || isset($index['national_id']) || isset($index['datetime']) || isset($index['date']) || isset($index['time']);
        $rows = [];
        for ($i = $hasHeader ? 1 : 0; $i < count($lines); $i++) {
            if (trim((string) $lines[$i]) === '') {
                continue;
            }
            $cols = array_map('trim', str_getcsv((string) $lines[$i], $sep));
            if ($hasHeader) {
                $pick = fn (string $f) => isset($index[$f]) ? ($cols[$index[$f]] ?? '') : '';
                $datetime = $pick('datetime');
                if ($datetime === '' && $pick('date') !== '') {
                    $datetime = trim($pick('date').' '.$pick('time'));
                } elseif ($datetime === '') {
                    // ZKTeco "Time" column usually carries the full date and time.
                    $datetime = $pick('time');
                }
                $rows[] = [
                    'employee_code' => $pick('code'),
                    'national_id' => $pick('national_id'),
                    'punched_at' => $datetime,
                    'direction' => $pick('direction') !== '' ? $pick('direction') : 'in',
                    'device' => $pick('device'),
                    'line' => $i + 1,
                ];
            } else {
                $rows[] = [
                    'employee_code' => $cols[0] ?? '',
                    'punched_at' => $cols[1] ?? '',
                    'direction' => ($cols[2] ?? '') !== '' ? $cols[2] : 'in',
                    'device' => $cols[3] ?? '',
                    'line' => $i + 1,
                ];
            }
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

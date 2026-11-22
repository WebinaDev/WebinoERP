<?php

namespace Modules\Hrm\Services;

use Carbon\Carbon;
use Modules\Hrm\Entities\HrmAttendanceDevice;
use Modules\Hrm\Entities\HrmAttendancePunch;
use Modules\Hrm\Entities\HrmAttendanceRecord;
use Modules\Hrm\Entities\HrmEmployee;

class HrmAttendanceDeviceConnectorService
{
    /**
     * Standard ZKTeco / Hikvision ADMS (Automatic Data Master Server) / Push Protocol Parser
     * Handles raw push payload:
     * e.g. "USERID=101\tCHECKTIME=2026-11-22 08:15:22\tCHECKTYPE=I\tVERIFYCODE=1"
     *
     * @return array{parsed_count: int, applied_count: int, failed_count: int}
     */
    public function processAdmsPushLog(HrmAttendanceDevice $device, string $rawBody): array
    {
        $lines = preg_split('/[\r\n]+/', trim($rawBody));
        $parsed = 0;
        $applied = 0;
        $failed = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $fields = [];
            // Handle tab-separated or comma-separated device logs
            $delimiter = str_contains($line, "\t") ? "\t" : (str_contains($line, ',') ? ',' : '&');
            $parts = explode($delimiter, $line);

            foreach ($parts as $part) {
                if (str_contains($part, '=')) {
                    [$key, $val] = explode('=', $part, 2);
                    $fields[strtoupper(trim($key))] = trim($val);
                }
            }

            $userId = $fields['USERID'] ?? $fields['PIN'] ?? $fields['ENROLL_NUMBER'] ?? null;
            $timestamp = $fields['CHECKTIME'] ?? $fields['TIME'] ?? $fields['TIMESTAMP'] ?? null;
            $type = strtoupper($fields['CHECKTYPE'] ?? $fields['TYPE'] ?? 'I'); // I = in, O = out

            if (! $userId || ! $timestamp) {
                $failed++;
                continue;
            }

            $parsed++;

            try {
                $punchTime = Carbon::parse($timestamp);
            } catch (\Throwable) {
                $failed++;
                continue;
            }

            // Find mapped employee
            $employee = HrmEmployee::query()
                ->where('employee_code', $userId)
                ->orWhere('id', (int) $userId)
                ->first();

            $punch = HrmAttendancePunch::query()->create([
                'device_id' => $device->id,
                'employee_id' => $employee?->id,
                'raw_code' => $userId,
                'punched_at' => $punchTime,
                'punch_type' => in_array($type, ['O', 'OUT', '1']) ? 'out' : 'in',
                'status' => $employee ? 'applied' : 'unmatched',
                'source' => 'adms_push',
            ]);

            if ($employee) {
                $applied++;
                $this->syncPunchToDailyRecord($employee, $punch);
            }
        }

        return [
            'parsed_count' => $parsed,
            'applied_count' => $applied,
            'failed_count' => $failed,
        ];
    }

    /**
     * Map individual biometric punch into the consolidated daily attendance record
     */
    private function syncPunchToDailyRecord(HrmEmployee $employee, HrmAttendancePunch $punch): void
    {
        $date = $punch->punched_at->toDateString();
        $time = $punch->punched_at->format('H:i:s');

        $record = HrmAttendanceRecord::query()->firstOrCreate(
            [
                'employee_id' => $employee->id,
                'date' => $date,
            ],
            [
                'status' => 'present',
            ]
        );

        if ($punch->punch_type === 'in') {
            if (! $record->clock_in || $time < $record->clock_in) {
                $record->clock_in = $time;
            }
        } else {
            if (! $record->clock_out || $time > $record->clock_out) {
                $record->clock_out = $time;
            }
        }

        // Recalculate duration if both clock_in and clock_out are set
        if ($record->clock_in && $record->clock_out) {
            $in = Carbon::parse("{$date} {$record->clock_in}");
            $out = Carbon::parse("{$date} {$record->clock_out}");
            if ($out->greaterThan($in)) {
                $record->worked_hours = round($out->diffInMinutes($in) / 60, 2);
            }
        }

        $record->save();
    }
}

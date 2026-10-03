<?php

namespace Modules\Hrm\Services;

use Illuminate\Support\Facades\Schema;
use Modules\Hrm\Entities\HrmEmploymentDecree;
use Modules\Hrm\Entities\HrmPayrollItem;
use Modules\Hrm\Entities\HrmPayrollRun;

/**
 * Social Security (تأمین اجتماعی) list export for a payroll run.
 */
class HrmInsuranceListService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function rows(HrmPayrollRun $run, ?int $workshopId = null): array
    {
        $run->loadMissing(['items.employee.profile']);
        $out = [];
        foreach ($run->items as $item) {
            /** @var HrmPayrollItem $item */
            $emp = $item->employee;
            if (! $emp) {
                continue;
            }
            $b = $item->breakdown ?? [];
            if (array_key_exists('insurance_applicable', $b) && ! $b['insurance_applicable']) {
                continue;
            }
            $decree = null;
            if (Schema::hasTable('hrm_employment_decrees')) {
                $dq = HrmEmploymentDecree::query()->where('employee_id', $emp->id)->orderByDesc('id');
                if ($workshopId) {
                    $dq->where('workshop_id', $workshopId);
                }
                $decree = $dq->first();
            }
            if ($workshopId && $decree && (int) $decree->workshop_id !== $workshopId) {
                continue;
            }
            $profile = $emp->profile;
            $custom = $profile?->custom_fields ?? [];
            $out[] = [
                'workshop_id' => $decree?->workshop_id,
                'employee_id' => $emp->id,
                'employee_code' => $emp->employee_code,
                'first_name' => $emp->first_name,
                'last_name' => $emp->last_name,
                'national_id' => $profile?->national_id,
                'insurance_no' => $custom['insurance_no'] ?? $custom['ssn'] ?? null,
                'job_title' => $decree?->job_title ?? $emp->position,
                'department' => $decree?->department ?? $emp->department,
                'insurable' => $b['insurable'] ?? null,
                'employee_insurance' => $b['employee_insurance'] ?? null,
                'employer_insurance' => $b['employer_insurance'] ?? null,
                'unemployment_insurance' => $b['unemployment_insurance'] ?? null,
                'gross' => $item->gross,
                'net' => $item->net,
                'year' => $run->year,
                'month' => $run->month,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function toCsv(array $rows): string
    {
        $headers = [
            'workshop_id', 'employee_code', 'first_name', 'last_name', 'national_id', 'insurance_no',
            'job_title', 'department', 'insurable', 'employee_insurance', 'employer_insurance',
            'unemployment_insurance', 'gross', 'net', 'year', 'month',
        ];
        $fh = fopen('php://temp', 'r+');
        // UTF-8 BOM for Excel
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, $headers);
        foreach ($rows as $row) {
            fputcsv($fh, array_map(fn ($h) => $row[$h] ?? '', $headers));
        }
        rewind($fh);
        $csv = stream_get_contents($fh) ?: '';
        fclose($fh);

        return $csv;
    }

    /**
     * DSKWOR-style text list (comma-separated). This is a readable subset of the
     * Social Security worker list, not the proprietary binary/DBF diskette.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function toDiskette(HrmPayrollRun $run, array $rows): string
    {
        [$yy, $mm] = $this->jalaliYearMonth((int) $run->year, (int) $run->month);
        $headers = [
            'DSW_ID', 'DSW_YY', 'DSW_MM', 'DSW_LISTNO', 'DSW_ID1', 'DSW_FNAME', 'DSW_LNAME',
            'DSW_DNAME', 'DSW_IDNO', 'DSW_BDATE', 'DSW_SEX', 'DSW_NAT', 'DSW_OCP',
            'DSW_SDATE', 'DSW_DD', 'DSW_ROOZ', 'DSW_MAH', 'DSW_MAZ', 'DSW_MASH', 'DSW_TOTL',
            'DSW_BIME', 'DSW_PRATE', 'DSW_JOB', 'PER_NATCOD',
        ];
        $lines = [implode(',', $headers)];
        foreach ($rows as $row) {
            $days = $this->workedDays((int) ($row['employee_id'] ?? 0), (int) $run->year, (int) $run->month);
            $insurable = (float) ($row['insurable'] ?? 0);
            $daily = $days > 0 ? (int) round($insurable / $days) : (int) round($insurable / 30);
            $emp = \Modules\Hrm\Entities\HrmEmployee::query()->with('profile')->find($row['employee_id'] ?? 0);
            $profile = $emp?->profile;
            $custom = $profile?->custom_fields ?? [];
            $sex = match ((string) ($profile?->gender ?? '')) {
                'male', 'm', 'مرد' => '1',
                'female', 'f', 'زن' => '2',
                default => '',
            };
            $values = [
                $row['insurance_no'] ?? '',
                $yy,
                str_pad((string) $mm, 2, '0', STR_PAD_LEFT),
                $run->id,
                $row['workshop_id'] ?? ($custom['workshop_code'] ?? ''),
                $row['first_name'] ?? '',
                $row['last_name'] ?? '',
                $custom['father_name'] ?? '',
                $custom['id_no'] ?? ($row['national_id'] ?? ''),
                optional($profile?->birth_date)->format('Ymd') ?? '',
                $sex,
                $custom['nationality'] ?? 'ایرانی',
                $row['job_title'] ?? '',
                optional($emp?->hire_date)->format('Ymd') ?? '',
                $days,
                $daily,
                (int) round($insurable),
                (int) round((float) ($custom['insurance_benefits'] ?? 0)),
                (int) round($insurable),
                (int) round((float) ($row['gross'] ?? 0)),
                (int) round((float) ($row['employee_insurance'] ?? 0)),
                7,
                $custom['job_code'] ?? '',
                $row['national_id'] ?? '',
            ];
            $lines[] = implode(',', array_map(fn ($v) => str_replace([",", "\n", "\r"], ' ', (string) $v), $values));
        }

        return implode("\r\n", $lines)."\r\n";
    }

    private function workedDays(int $employeeId, int $year, int $month): int
    {
        if ($employeeId === 0 || $year < 1700) {
            return 30;
        }
        $count = \Modules\Hrm\Entities\HrmAttendanceRecord::query()
            ->where('employee_id', $employeeId)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->whereIn('status', ['present', 'checked_in', 'worked', 'late'])
            ->count();

        return $count > 0 ? $count : 30;
    }

    /**
     * @return array{0:int,1:int}
     */
    private function jalaliYearMonth(int $year, int $month): array
    {
        if ($year < 1700) {
            return [$year, $month];
        }
        $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy = $year - 1600;
        $gm = $month - 1;
        $gd = 1 - 1;
        $g_day_no = 365 * $gy + intdiv($gy + 3, 4) - intdiv($gy + 99, 100) + intdiv($gy + 399, 400);
        $g_day_no += $g_d_m[$gm] + $gd;
        if ($gm > 1 && (($gy + 1600) % 4 === 0 && (($gy + 1600) % 100 !== 0 || ($gy + 1600) % 400 === 0))) {
            $g_day_no++;
        }
        $g_day_no -= 79;
        $j_np = intdiv($g_day_no, 12053);
        $g_day_no %= 12053;
        $jy = 979 + 33 * $j_np + 4 * intdiv($g_day_no, 1461);
        $g_day_no %= 1461;
        if ($g_day_no >= 366) {
            $jy += intdiv($g_day_no - 1, 365);
            $g_day_no = ($g_day_no - 1) % 365;
        }
        $jm = $g_day_no < 186 ? 1 + intdiv($g_day_no, 31) : 7 + intdiv($g_day_no - 186, 30);

        return [$jy, $jm];
    }
}

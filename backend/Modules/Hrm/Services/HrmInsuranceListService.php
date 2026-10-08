<?php

namespace Modules\Hrm\Services;

use Illuminate\Support\Facades\Schema;
use Modules\Hrm\Entities\HrmAttendanceRecord;
use Modules\Hrm\Entities\HrmEmploymentDecree;
use Modules\Hrm\Entities\HrmPayrollItem;
use Modules\Hrm\Entities\HrmPayrollRun;
use Modules\Hrm\Support\DbfWriter;
use Modules\Hrm\Support\HrmPeriod;
use Modules\Hrm\Support\IranianNationalId;
use ZipArchive;

/**
 * Social Security (تأمین اجتماعی) monthly list: DSKKAR00 (workshop header) + DSKWOR00 (workers).
 *
 * Output formats: real dBase III .DBF files (Windows-1256 text), a ZIP with both, readable
 * comma-separated TXT with the same columns, and CSV. Field layout follows the widely used
 * DSKKAR00/DSKWOR00 structure; it has not been certified by the SSO.
 */
class HrmInsuranceListService
{
    public const RUN_STATUSES = ['calculated', 'approved', 'paid'];

    /** @var list<array{0: string, 1: string, 2: int, 3?: int}> */
    public const DSKKAR_FIELDS = [
        ['DSK_ID', 'C', 10], ['DSK_NAME', 'C', 100], ['DSK_FARM', 'C', 100], ['DSK_ADRS', 'C', 100],
        ['DSK_KIND', 'N', 1], ['DSK_YY', 'N', 2], ['DSK_MM', 'N', 2], ['DSK_LISTNO', 'C', 12],
        ['DSK_DISC', 'C', 100], ['DSK_NUM', 'N', 5], ['DSK_TDD', 'N', 6], ['DSK_TROOZ', 'N', 12],
        ['DSK_TMAH', 'N', 12], ['DSK_TMAZ', 'N', 12], ['DSK_TMASH', 'N', 12], ['DSK_TTOTL', 'N', 12],
        ['DSK_TBIME', 'N', 12], ['DSK_TKOSO', 'N', 12], ['DSK_BIC', 'N', 12], ['DSK_RATE', 'N', 5],
        ['DSK_PRATE', 'N', 2], ['DSK_BIMH', 'N', 12], ['MON_PYM', 'C', 3],
    ];

    /** @var list<array{0: string, 1: string, 2: int, 3?: int}> */
    public const DSKWOR_FIELDS = [
        ['DSW_ID', 'C', 10], ['DSW_YY', 'N', 2], ['DSW_MM', 'N', 2], ['DSW_LISTNO', 'C', 12],
        ['DSW_ID1', 'C', 10], ['DSW_FNAME', 'C', 100], ['DSW_LNAME', 'C', 100], ['DSW_DNAME', 'C', 100],
        ['DSW_IDNO', 'C', 15], ['DSW_IDPLC', 'C', 100], ['DSW_IDATE', 'C', 8], ['DSW_BDATE', 'C', 8],
        ['DSW_SEX', 'C', 3], ['DSW_NAT', 'C', 10], ['DSW_OCP', 'C', 100], ['DSW_SDATE', 'C', 8],
        ['DSW_EDATE', 'C', 8], ['DSW_DD', 'N', 2], ['DSW_ROOZ', 'N', 12], ['DSW_MAH', 'N', 12],
        ['DSW_MAZ', 'N', 12], ['DSW_MASH', 'N', 12], ['DSW_TOTL', 'N', 12], ['DSW_BIME', 'N', 12],
        ['DSW_PRATE', 'N', 2], ['DSW_JOB', 'C', 6], ['PER_NATCOD', 'C', 10],
    ];

    /**
     * Rows for one payroll run (legacy endpoint).
     *
     * @return list<array<string, mixed>>
     */
    public function rows(HrmPayrollRun $run, ?int $workshopId = null): array
    {
        $run->loadMissing(['items.employee.profile']);

        return $this->buildRows($run->items, (int) $run->year, (int) $run->month, $workshopId);
    }

    /**
     * Rows for every calculated/approved/paid run of a period (bulk runs and single payslips).
     * The latest item wins when an employee appears in more than one run.
     *
     * @return list<array<string, mixed>>
     */
    public function rowsForPeriod(int $year, int $month, ?int $workshopId = null): array
    {
        $runIds = HrmPayrollRun::query()
            ->where('year', $year)
            ->where('month', $month)
            ->whereIn('status', self::RUN_STATUSES)
            ->pluck('id');
        $items = HrmPayrollItem::query()
            ->with('employee.profile')
            ->whereIn('payroll_run_id', $runIds)
            ->orderBy('id')
            ->get()
            ->keyBy('employee_id')
            ->values();

        return $this->buildRows($items, $year, $month, $workshopId);
    }

    /**
     * @return array<string, mixed>
     */
    public function header(int $year, int $month): array
    {
        $s = app(IranianPayrollCalculator::class)->settings();
        [$jy, $jm] = HrmPeriod::toJalaliPeriod($year, $month);

        return [
            'workshop_code' => (string) ($s['workshop_code'] ?? ''),
            'workshop_name' => (string) ($s['workshop_name'] ?? ''),
            'employer_name' => (string) ($s['employer_name'] ?? ''),
            'workshop_address' => (string) ($s['workshop_address'] ?? ''),
            'list_no' => (string) (($s['sso_list_no'] ?? '') !== '' ? $s['sso_list_no'] : '01'),
            'contract_row' => (string) (($s['sso_contract_row'] ?? '') !== '' ? $s['sso_contract_row'] : '000'),
            'list_description' => (string) ($s['sso_list_description'] ?? ''),
            'employer_rate' => (float) ($s['employer_insurance_percent'] ?? 20),
            'jalali_year' => $jy,
            'jalali_month' => $jm,
            'yy' => $jy % 100,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, int>
     */
    public function totals(array $rows): array
    {
        $t = ['count' => count($rows), 'days' => 0, 'daily_wage' => 0, 'monthly_wage' => 0, 'benefits' => 0,
            'insurable' => 0, 'gross' => 0, 'employee_insurance' => 0, 'employer_insurance' => 0, 'unemployment_insurance' => 0];
        foreach ($rows as $r) {
            foreach (array_keys($t) as $k) {
                if ($k !== 'count') {
                    $t[$k] += (int) round((float) ($r[$k] ?? 0));
                }
            }
        }

        return $t;
    }

    /**
     * @param  iterable<HrmPayrollItem>  $items
     * @return list<array<string, mixed>>
     */
    private function buildRows(iterable $items, int $year, int $month, ?int $workshopId): array
    {
        $settings = app(IranianPayrollCalculator::class)->settings();
        $out = [];
        foreach ($items as $item) {
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
            if ($workshopId && (! $decree || (int) $decree->workshop_id !== $workshopId)) {
                continue;
            }
            $profile = $emp->profile;
            $custom = is_array($profile?->custom_fields) ? $profile->custom_fields : [];
            $insuranceNo = $profile?->insurance_number ?: ($custom['insurance_no'] ?? $custom['ssn'] ?? null);
            $workshopCode = ($settings['workshop_code'] ?? '') !== '' ? $settings['workshop_code'] : ($custom['workshop_code'] ?? $decree?->workshop_id);
            $national = IranianNationalId::normalize((string) ($profile?->national_id ?? ''));

            $days = $this->workedDays($emp->id, $year, $month);
            $insurable = (int) round((float) ($b['insurable'] ?? 0));
            $base = (int) round((float) ($b['base'] ?? $insurable));
            $monthly = min($base, $insurable);
            $benefits = max(0, $insurable - $monthly);
            $sex = match (mb_strtolower((string) ($profile?->gender ?? ''))) {
                'male', 'm', 'مرد' => 'مرد',
                'female', 'f', 'زن' => 'زن',
                default => '',
            };
            $warnings = [];
            if (! $insuranceNo) {
                $warnings[] = 'missing_insurance_no';
            }
            if ($national === '') {
                $warnings[] = 'missing_national_id';
            } elseif (! IranianNationalId::isValid($national)) {
                $warnings[] = 'invalid_national_id';
            }
            if (! $workshopCode) {
                $warnings[] = 'missing_workshop_code';
            }

            $out[] = [
                'workshop_id' => $decree?->workshop_id,
                'workshop_code' => $workshopCode,
                'workshop_name' => $settings['workshop_name'] ?? null,
                'employer_name' => $settings['employer_name'] ?? null,
                'employee_id' => $emp->id,
                'employee_code' => $emp->employee_code,
                'first_name' => $emp->first_name,
                'last_name' => $emp->last_name,
                'father_name' => $profile?->father_name ?: ($custom['father_name'] ?? ''),
                'national_id' => $national !== '' ? $national : null,
                'id_no' => $custom['id_no'] ?? $national,
                'id_place' => $custom['id_place'] ?? '',
                'birth_date' => HrmPeriod::jalaliDate8($profile?->birth_date),
                'sex' => $sex,
                'nationality' => $custom['nationality'] ?? 'ایرانی',
                'insurance_no' => $insuranceNo,
                'job_title' => $decree?->job_title ?? $emp->position,
                'job_code' => $custom['job_code'] ?? '',
                'department' => $decree?->department ?? $emp->department,
                'start_date' => HrmPeriod::jalaliDate8($emp->hire_date ?? $emp->contract_start_date ?? null),
                'end_date' => in_array((string) $emp->status, ['terminated', 'inactive'], true) ? HrmPeriod::jalaliDate8($emp->contract_end_date ?? null) : '',
                'days' => $days,
                'daily_wage' => $days > 0 ? (int) round($monthly / $days) : 0,
                'monthly_wage' => $monthly,
                'benefits' => $benefits,
                'insurable' => $insurable,
                'employee_insurance' => (int) round((float) ($b['employee_insurance'] ?? 0)),
                'employer_insurance' => (int) round((float) ($b['employer_insurance'] ?? 0)),
                'unemployment_insurance' => (int) round((float) ($b['unemployment_insurance'] ?? 0)),
                'gross' => (int) round((float) $item->gross),
                'net' => (int) round((float) $item->net),
                'year' => $year,
                'month' => $month,
                'payroll_run_id' => $item->payroll_run_id,
                'warnings' => $warnings,
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
            'workshop_id', 'workshop_code', 'employee_code', 'first_name', 'last_name', 'father_name', 'national_id', 'insurance_no',
            'job_title', 'department', 'days', 'daily_wage', 'monthly_wage', 'benefits', 'insurable', 'employee_insurance',
            'employer_insurance', 'unemployment_insurance', 'gross', 'net', 'year', 'month',
        ];

        return $this->csv($headers, array_map(fn ($row) => array_map(fn ($h) => $row[$h] ?? '', $headers), $rows));
    }

    /**
     * Persian column list (لیست بیمه) — CSV with FA headers for payroll clerks.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function toList(array $rows): string
    {
        $headers = ['کد کارگاه', 'کد پرسنلی', 'نام', 'نام خانوادگی', 'نام پدر', 'کد ملی', 'شماره بیمه', 'شغل', 'روز کارکرد',
            'دستمزد روزانه', 'دستمزد ماهانه', 'مزایای مشمول', 'دستمزد و مزایای مشمول', 'سهم بیمه شده', 'سهم کارفرما', 'بیمه بیکاری', 'ناخالص', 'خالص'];
        $data = array_map(fn ($r) => [
            $r['workshop_code'] ?? '', $r['employee_code'] ?? '', $r['first_name'] ?? '', $r['last_name'] ?? '',
            $r['father_name'] ?? '', $r['national_id'] ?? '', $r['insurance_no'] ?? '', $r['job_title'] ?? '',
            $r['days'] ?? '', $r['daily_wage'] ?? '', $r['monthly_wage'] ?? '', $r['benefits'] ?? '', $r['insurable'] ?? '',
            $r['employee_insurance'] ?? '', $r['employer_insurance'] ?? '', $r['unemployment_insurance'] ?? '',
            $r['gross'] ?? '', $r['net'] ?? '',
        ], $rows);

        return $this->csv($headers, $data);
    }

    /**
     * @param  array<string, mixed>  $header
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function dskworRecords(array $header, array $rows): array
    {
        return array_map(fn ($r) => [
            'DSW_ID' => (string) ($r['workshop_code'] ?: $header['workshop_code']),
            'DSW_YY' => $header['yy'],
            'DSW_MM' => $header['jalali_month'],
            'DSW_LISTNO' => $header['list_no'],
            'DSW_ID1' => (string) ($r['insurance_no'] ?? ''),
            'DSW_FNAME' => (string) ($r['first_name'] ?? ''),
            'DSW_LNAME' => (string) ($r['last_name'] ?? ''),
            'DSW_DNAME' => (string) ($r['father_name'] ?? ''),
            'DSW_IDNO' => (string) ($r['id_no'] ?? ''),
            'DSW_IDPLC' => (string) ($r['id_place'] ?? ''),
            'DSW_IDATE' => '',
            'DSW_BDATE' => (string) ($r['birth_date'] ?? ''),
            'DSW_SEX' => (string) ($r['sex'] ?? ''),
            'DSW_NAT' => (string) ($r['nationality'] ?? ''),
            'DSW_OCP' => (string) ($r['job_title'] ?? ''),
            'DSW_SDATE' => (string) ($r['start_date'] ?? ''),
            'DSW_EDATE' => (string) ($r['end_date'] ?? ''),
            'DSW_DD' => (int) ($r['days'] ?? 0),
            'DSW_ROOZ' => (int) ($r['daily_wage'] ?? 0),
            'DSW_MAH' => (int) ($r['monthly_wage'] ?? 0),
            'DSW_MAZ' => (int) ($r['benefits'] ?? 0),
            'DSW_MASH' => (int) ($r['insurable'] ?? 0),
            'DSW_TOTL' => (int) ($r['gross'] ?? 0),
            'DSW_BIME' => (int) ($r['employee_insurance'] ?? 0),
            'DSW_PRATE' => 0,
            'DSW_JOB' => (string) ($r['job_code'] ?? ''),
            'PER_NATCOD' => (string) ($r['national_id'] ?? ''),
        ], $rows);
    }

    /**
     * @param  array<string, mixed>  $header
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    public function dskkarRecord(array $header, array $rows): array
    {
        $t = $this->totals($rows);

        return [
            'DSK_ID' => $header['workshop_code'],
            'DSK_NAME' => $header['workshop_name'],
            'DSK_FARM' => $header['employer_name'],
            'DSK_ADRS' => $header['workshop_address'],
            'DSK_KIND' => 0,
            'DSK_YY' => $header['yy'],
            'DSK_MM' => $header['jalali_month'],
            'DSK_LISTNO' => $header['list_no'],
            'DSK_DISC' => $header['list_description'],
            'DSK_NUM' => $t['count'],
            'DSK_TDD' => $t['days'],
            'DSK_TROOZ' => $t['daily_wage'],
            'DSK_TMAH' => $t['monthly_wage'],
            'DSK_TMAZ' => $t['benefits'],
            'DSK_TMASH' => $t['insurable'],
            'DSK_TTOTL' => $t['gross'],
            'DSK_TBIME' => $t['employee_insurance'],
            'DSK_TKOSO' => $t['employer_insurance'],
            'DSK_BIC' => $t['unemployment_insurance'],
            'DSK_RATE' => (int) round($header['employer_rate']),
            'DSK_PRATE' => 0,
            'DSK_BIMH' => 0,
            'MON_PYM' => $header['contract_row'],
        ];
    }

    /**
     * @param  array<string, mixed>  $header
     * @param  list<array<string, mixed>>  $rows
     */
    public function dskworDbf(array $header, array $rows, string $encoding = 'CP1256'): string
    {
        return DbfWriter::build(self::DSKWOR_FIELDS, $this->dskworRecords($header, $rows), $encoding);
    }

    /**
     * @param  array<string, mixed>  $header
     * @param  list<array<string, mixed>>  $rows
     */
    public function dskkarDbf(array $header, array $rows, string $encoding = 'CP1256'): string
    {
        return DbfWriter::build(self::DSKKAR_FIELDS, [$this->dskkarRecord($header, $rows)], $encoding);
    }

    /**
     * @param  array<string, mixed>  $header
     * @param  list<array<string, mixed>>  $rows
     */
    public function dskworText(array $header, array $rows): string
    {
        return $this->delimited(self::DSKWOR_FIELDS, $this->dskworRecords($header, $rows));
    }

    /**
     * @param  array<string, mixed>  $header
     * @param  list<array<string, mixed>>  $rows
     */
    public function dskkarText(array $header, array $rows): string
    {
        return $this->delimited(self::DSKKAR_FIELDS, [$this->dskkarRecord($header, $rows)]);
    }

    /**
     * ZIP with DSKKAR00.DBF + DSKWOR00.DBF.
     *
     * @param  array<string, mixed>  $header
     * @param  list<array<string, mixed>>  $rows
     */
    public function zip(array $header, array $rows): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'sso');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $zip->addFromString('DSKKAR00.DBF', $this->dskkarDbf($header, $rows));
        $zip->addFromString('DSKWOR00.DBF', $this->dskworDbf($header, $rows));
        $zip->close();
        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $bytes;
    }

    /** Legacy: DSKWOR text for a run. @param list<array<string, mixed>> $rows */
    public function toDiskette(HrmPayrollRun $run, array $rows): string
    {
        return $this->dskworText($this->header((int) $run->year, (int) $run->month), $rows);
    }

    /** Legacy: DSKKAR text for a run. @param list<array<string, mixed>> $rows */
    public function toDskkar(HrmPayrollRun $run, array $rows): string
    {
        return $this->dskkarText($this->header((int) $run->year, (int) $run->month), $rows);
    }

    /**
     * @param  list<array{0: string, 1: string, 2: int, 3?: int}>  $fields
     * @param  list<array<string, mixed>>  $records
     */
    private function delimited(array $fields, array $records): string
    {
        $names = array_map(fn ($f) => $f[0], $fields);
        $lines = [implode(',', $names)];
        foreach ($records as $rec) {
            $lines[] = implode(',', array_map(fn ($n) => str_replace([',', "\n", "\r"], ' ', (string) ($rec[$n] ?? '')), $names));
        }

        return "\xEF\xBB\xBF".implode("\r\n", $lines)."\r\n";
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<mixed>>  $rows
     */
    private function csv(array $headers, array $rows): string
    {
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, $headers);
        foreach ($rows as $row) {
            fputcsv($fh, array_map(fn ($v) => is_array($v) ? implode('|', $v) : (string) ($v ?? ''), $row));
        }
        rewind($fh);
        $csv = stream_get_contents($fh) ?: '';
        fclose($fh);

        return $csv;
    }

    private function workedDays(int $employeeId, int $year, int $month): int
    {
        $full = HrmPeriod::daysInPeriod($year, $month);
        if ($employeeId === 0 || ! Schema::hasTable('hrm_attendance_records')) {
            return min(31, $full);
        }
        [$from, $to] = HrmPeriod::gregorianRange($year, $month);
        $count = HrmAttendanceRecord::query()
            ->where('employee_id', $employeeId)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereIn('status', ['present', 'checked_in', 'worked', 'late', 'remote', 'mission'])
            ->count();

        return min(31, $count > 0 ? $count : $full);
    }
}

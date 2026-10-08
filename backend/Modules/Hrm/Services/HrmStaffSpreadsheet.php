<?php

namespace Modules\Hrm\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmEmployeeProfile;
use Modules\Hrm\Support\HrmPeriod;
use Modules\Hrm\Support\IranianNationalId;
use Modules\Hrm\Support\SimpleSpreadsheet;

/**
 * Staff import/export (CSV with UTF-8 BOM or XLSX). Headers may be Persian or English.
 * Errors are returned as codes so the UI can translate them.
 */
class HrmStaffSpreadsheet
{
    public const STATUSES = ['active', 'inactive', 'probation', 'terminated'];

    /** @var array<string, string> */
    public const HEADERS_FA = [
        'employee_code' => 'کد پرسنلی',
        'first_name' => 'نام',
        'last_name' => 'نام خانوادگی',
        'national_id' => 'کد ملی',
        'father_name' => 'نام پدر',
        'mobile' => 'موبایل',
        'email' => 'ایمیل',
        'department' => 'واحد',
        'position' => 'سمت',
        'status' => 'وضعیت',
        'hire_date' => 'تاریخ استخدام',
        'base_salary' => 'حقوق پایه',
        'insurance_number' => 'شماره بیمه',
        'sheba' => 'شبا',
    ];

    /** @var array<string, list<string>> */
    private array $aliases = [
        'employee_code' => ['employee_code', 'code', 'personnel_code', 'کد پرسنلی', 'کد کارمندی'],
        'first_name' => ['first_name', 'name', 'نام'],
        'last_name' => ['last_name', 'family', 'surname', 'نام خانوادگی'],
        'national_id' => ['national_id', 'national_code', 'کد ملی', 'کدملی', 'شماره ملی'],
        'father_name' => ['father_name', 'نام پدر'],
        'mobile' => ['mobile', 'phone', 'موبایل', 'تلفن همراه', 'شماره موبایل'],
        'email' => ['email', 'ایمیل', 'پست الکترونیک'],
        'department' => ['department', 'واحد', 'دپارتمان', 'بخش'],
        'position' => ['position', 'job_title', 'سمت', 'عنوان شغلی'],
        'status' => ['status', 'وضعیت'],
        'hire_date' => ['hire_date', 'تاریخ استخدام', 'تاریخ شروع'],
        'base_salary' => ['base_salary', 'salary', 'حقوق پایه', 'حقوق'],
        'insurance_number' => ['insurance_number', 'insurance_no', 'شماره بیمه', 'شماره بیمه تامین اجتماعی', 'شماره بیمه تأمین اجتماعی'],
        'sheba' => ['sheba', 'iban', 'شبا', 'شماره شبا'],
    ];

    /** @var array<string, string> */
    private array $statusAliases = [
        'فعال' => 'active', 'غیرفعال' => 'inactive', 'غیر فعال' => 'inactive',
        'آزمایشی' => 'probation', 'دوره آزمایشی' => 'probation', 'خاتمه همکاری' => 'terminated', 'منفک' => 'terminated',
    ];

    /** @var array<string, string> */
    private array $statusLabels = [
        'active' => 'فعال', 'inactive' => 'غیرفعال', 'probation' => 'آزمایشی', 'terminated' => 'خاتمه همکاری',
    ];

    /**
     * @return list<list<string|int|float|null>>
     */
    public function rows(): array
    {
        $out = [array_values(self::HEADERS_FA)];
        $employees = HrmEmployee::query()->with('profile')->orderBy('employee_code')->get();
        foreach ($employees as $emp) {
            $p = $emp->profile;
            $out[] = [
                $emp->employee_code,
                $emp->first_name,
                $emp->last_name,
                $p?->national_id,
                $p?->father_name,
                $emp->mobile,
                $emp->email,
                $emp->department,
                $emp->position,
                $this->statusLabels[(string) $emp->status] ?? $emp->status,
                $emp->hire_date ? $this->jalaliDash($emp->hire_date) : '',
                $emp->base_salary !== null ? (string) (int) round((float) $emp->base_salary) : '',
                $p?->insurance_number ?: (is_array($p?->custom_fields) ? ($p->custom_fields['insurance_no'] ?? '') : ''),
                $p?->sheba,
            ];
        }

        return $out;
    }

    /**
     * @return list<list<string>>
     */
    public function template(): array
    {
        return [
            array_values(self::HEADERS_FA),
            ['1001', 'علی', 'رضایی', '0012345679', 'محمد', '09121234567', 'ali@example.com', 'مالی', 'حسابدار', 'فعال', '1405/01/15', '150000000', '1234567890', 'IR120170000000123456789001'],
        ];
    }

    /**
     * @return array{created: int, updated: int, skipped: int, total: int, dry_run: bool, errors: list<array{row: int, field: ?string, code: string, value?: string}>}
     */
    public function import(string $path, string $filename, bool $dryRun = false): array
    {
        $table = SimpleSpreadsheet::read($path, $filename);
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'total' => 0, 'dry_run' => $dryRun, 'errors' => []];
        if (count($table) < 2) {
            $result['errors'][] = ['row' => 1, 'field' => null, 'code' => 'empty_file'];

            return $result;
        }
        $map = $this->headerMap($table[0]);
        foreach (['employee_code', 'first_name', 'last_name'] as $required) {
            if (! isset($map[$required])) {
                $result['errors'][] = ['row' => 1, 'field' => $required, 'code' => 'missing_column'];
            }
        }
        if ($result['errors'] !== []) {
            return $result;
        }

        $seenCodes = [];
        $seenNational = [];
        foreach (array_slice($table, 1) as $offset => $line) {
            $rowNo = $offset + 2;
            if (trim(implode('', $line)) === '') {
                continue;
            }
            $result['total']++;
            $data = [];
            foreach ($map as $field => $idx) {
                $data[$field] = trim(HrmPeriod::asciiDigits((string) ($line[$idx] ?? '')));
            }
            $errors = $this->validateRow($data, $rowNo, $seenCodes, $seenNational);
            if ($errors !== []) {
                array_push($result['errors'], ...$errors);
                $result['skipped']++;
                continue;
            }
            $seenCodes[$data['employee_code']] = $rowNo;
            if (($data['national_id'] ?? '') !== '') {
                $seenNational[$data['national_id']] = $rowNo;
            }
            $exists = HrmEmployee::query()->where('employee_code', $data['employee_code'])->exists();
            if ($dryRun) {
                $exists ? $result['updated']++ : $result['created']++;
                continue;
            }
            try {
                DB::transaction(fn () => $this->save($data));
                $exists ? $result['updated']++ : $result['created']++;
            } catch (\Throwable $e) {
                report($e);
                $result['errors'][] = ['row' => $rowNo, 'field' => null, 'code' => 'save_failed'];
                $result['skipped']++;
            }
        }

        return $result;
    }

    /**
     * @param  array<string, string>  $data
     * @param  array<string, int>  $seenCodes
     * @param  array<string, int>  $seenNational
     * @return list<array{row: int, field: ?string, code: string, value?: string}>
     */
    private function validateRow(array &$data, int $rowNo, array $seenCodes, array $seenNational): array
    {
        $errors = [];
        $err = function (string $field, string $code) use (&$errors, $rowNo, $data) {
            $errors[] = ['row' => $rowNo, 'field' => $field, 'code' => $code, 'value' => (string) ($data[$field] ?? '')];
        };
        foreach (['employee_code', 'first_name', 'last_name'] as $f) {
            if (($data[$f] ?? '') === '') {
                $err($f, 'required');
            }
        }
        if (($data['employee_code'] ?? '') !== '' && isset($seenCodes[$data['employee_code']])) {
            $err('employee_code', 'duplicate_in_file');
        }
        $national = IranianNationalId::normalize($data['national_id'] ?? '');
        if (($data['national_id'] ?? '') !== '') {
            if (strlen($national) === 9 || strlen($national) === 8) {
                $national = str_pad($national, 10, '0', STR_PAD_LEFT);
            }
            if (! IranianNationalId::isValid($national)) {
                $err('national_id', 'invalid_national_id');
            } elseif (isset($seenNational[$national])) {
                $err('national_id', 'duplicate_in_file');
            } else {
                $owner = HrmEmployeeProfile::query()->where('national_id', $national)->with('employee')->first();
                if ($owner && $owner->employee && $owner->employee->employee_code !== ($data['employee_code'] ?? '')) {
                    $err('national_id', 'national_id_taken');
                }
            }
            $data['national_id'] = $national;
        }
        if (($data['mobile'] ?? '') !== '') {
            $mobile = preg_replace('/\D/', '', $data['mobile']) ?? '';
            if (str_starts_with($mobile, '98')) {
                $mobile = '0'.substr($mobile, 2);
            } elseif (str_starts_with($mobile, '9') && strlen($mobile) === 10) {
                $mobile = '0'.$mobile;
            }
            if (! preg_match('/^09\d{9}$/', $mobile)) {
                $err('mobile', 'invalid_mobile');
            }
            $data['mobile'] = $mobile;
        }
        if (($data['email'] ?? '') !== '' && ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $err('email', 'invalid_email');
        }
        if (($data['status'] ?? '') !== '') {
            $status = $this->statusAliases[$data['status']] ?? mb_strtolower($data['status']);
            if (! in_array($status, self::STATUSES, true)) {
                $err('status', 'invalid_status');
            }
            $data['status'] = $status;
        }
        if (($data['base_salary'] ?? '') !== '') {
            $salary = str_replace([',', '٬', ' '], '', $data['base_salary']);
            if (! is_numeric($salary) || (float) $salary < 0) {
                $err('base_salary', 'invalid_number');
            }
            $data['base_salary'] = $salary;
        }
        if (($data['hire_date'] ?? '') !== '') {
            $date = $this->parseDate($data['hire_date']);
            if (! $date) {
                $err('hire_date', 'invalid_date');
            }
            $data['hire_date'] = $date ?? '';
        }
        if (($data['sheba'] ?? '') !== '') {
            $sheba = strtoupper(str_replace([' ', '-'], '', $data['sheba']));
            if (! str_starts_with($sheba, 'IR')) {
                $sheba = 'IR'.$sheba;
            }
            if (! preg_match('/^IR\d{24}$/', $sheba)) {
                $err('sheba', 'invalid_sheba');
            }
            $data['sheba'] = $sheba;
        }
        if (($data['insurance_number'] ?? '') !== '' && ! preg_match('/^\d{6,12}$/', $data['insurance_number'])) {
            $err('insurance_number', 'invalid_insurance_number');
        }

        return $errors;
    }

    /**
     * @param  array<string, string>  $data
     */
    private function save(array $data): void
    {
        $emp = HrmEmployee::query()->where('employee_code', $data['employee_code'])->first();
        $payload = [
            'employee_code' => $data['employee_code'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
        ];
        foreach (['mobile', 'email', 'department', 'position', 'status', 'hire_date', 'base_salary'] as $f) {
            if (($data[$f] ?? '') !== '') {
                $payload[$f] = $f === 'base_salary' ? (float) $data[$f] : $data[$f];
            }
        }
        if ($emp) {
            $emp->update($payload);
        } else {
            $emp = HrmEmployee::query()->create($payload + ['status' => 'active', 'base_salary' => $payload['base_salary'] ?? 0]);
        }
        $profileFields = array_filter([
            'national_id' => $data['national_id'] ?? '',
            'father_name' => $data['father_name'] ?? '',
            'insurance_number' => $data['insurance_number'] ?? '',
            'sheba' => $data['sheba'] ?? '',
        ], fn ($v) => $v !== '');
        if ($profileFields !== []) {
            $profile = HrmEmployeeProfile::query()->firstOrNew(['employee_id' => $emp->id]);
            $profile->fill($profileFields);
            $profile->save();
        }
    }

    private function parseDate(string $value): ?string
    {
        $v = str_replace(['/', '.'], '-', trim($value));
        if (! preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $v, $m)) {
            return null;
        }
        [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        if ($mo < 1 || $mo > 12 || $d < 1 || $d > 31) {
            return null;
        }
        if ($y < 1700) {
            $g = \App\Support\CalendarDate::toGregorian($y, $mo, $d);
            [$y, $mo, $d] = [$g['gy'], $g['gm'], $g['gd']];
        }
        if (! checkdate($mo, $d, $y)) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $y, $mo, $d);
    }

    private function jalaliDash(mixed $date): string
    {
        $c = $date instanceof \DateTimeInterface ? Carbon::instance($date) : Carbon::parse((string) $date);
        $j = \App\Support\CalendarDate::toJalali((int) $c->year, (int) $c->month, (int) $c->day);

        return sprintf('%04d/%02d/%02d', $j['jy'], $j['jm'], $j['jd']);
    }

    /**
     * @param  list<string>  $header
     * @return array<string, int>
     */
    private function headerMap(array $header): array
    {
        $map = [];
        foreach ($header as $idx => $label) {
            $norm = $this->norm((string) $label);
            foreach ($this->aliases as $field => $names) {
                if (isset($map[$field])) {
                    continue;
                }
                foreach ($names as $name) {
                    if ($norm === $this->norm($name)) {
                        $map[$field] = $idx;
                        break;
                    }
                }
            }
        }

        return $map;
    }

    private function norm(string $value): string
    {
        $v = mb_strtolower(trim($value));
        $v = strtr($v, ['ي' => 'ی', 'ك' => 'ک', '‌' => ' ', 'أ' => 'ا']);

        return (string) preg_replace('/\s+/u', ' ', $v);
    }
}

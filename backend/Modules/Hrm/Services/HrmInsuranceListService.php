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
}

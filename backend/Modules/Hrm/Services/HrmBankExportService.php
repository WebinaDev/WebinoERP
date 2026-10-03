<?php

namespace Modules\Hrm\Services;

use Modules\Hrm\Entities\HrmPayrollRun;

/**
 * Generic Iranian salary transfer file (پایا / ساتنا style CSV).
 * Not a proprietary layout of a specific bank.
 */
class HrmBankExportService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function rows(HrmPayrollRun $run, string $channel = 'paya'): array
    {
        $channel = in_array($channel, ['paya', 'satna'], true) ? $channel : 'paya';
        $run->loadMissing(['items.employee.profile']);
        $out = [];
        foreach ($run->items as $item) {
            $emp = $item->employee;
            if (! $emp) {
                continue;
            }
            $profile = $emp->profile;
            $custom = $profile?->custom_fields ?? [];
            $sheba = $profile?->sheba ?: ($custom['sheba'] ?? $custom['iban'] ?? '');
            $sheba = strtoupper(preg_replace('/\s+/', '', (string) $sheba) ?? '');
            $net = (float) $item->net;
            $rowChannel = $channel;
            if ($channel === 'paya' && $net >= 1000000000) {
                $rowChannel = 'satna';
            }
            $out[] = [
                'employee_code' => $emp->employee_code,
                'receiver_name' => $profile?->account_holder ?: trim($emp->first_name.' '.$emp->last_name),
                'national_id' => $profile?->national_id,
                'sheba' => $sheba,
                'bank_name' => $profile?->bank_name,
                'amount' => $item->net,
                'payment_type' => $rowChannel,
                'description' => 'حقوق '.$run->year.'/'.$run->month.' '.$run->title,
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
            'employee_code', 'receiver_name', 'national_id', 'sheba', 'bank_name',
            'amount', 'payment_type', 'description', 'year', 'month',
        ];
        $fh = fopen('php://temp', 'r+');
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

<?php

namespace Modules\Hrm\Services;

use InvalidArgumentException;
use Modules\Hrm\Entities\HrmPayrollRun;
use Modules\Hrm\Entities\HrmPayrollItem;
use Modules\Hrm\Entities\HrmSubsidiaryCompany;

class HrmMultiBankExportEngine
{
    public function __construct(
        private readonly HrmStandardBankFormatService $standardService
    ) {}

    public function generateCertifiedBankFile(HrmPayrollRun $run, string $bank = 'paya_standard', ?string $companySheba = null): array
    {
        $items = HrmPayrollItem::query()->where('payroll_run_id', $run->id)->with('employee.profile')->get();
        if ($items->isEmpty()) {
            throw new InvalidArgumentException('رکوردی در این دوره حقوق برای خروجی بانکی وجود ندارد.');
        }

        // Strict subsidiary company resolution
        $sourceSheba = $companySheba;
        if (empty($sourceSheba) && $run->subsidiary_company_id && \Illuminate\Support\Facades\Schema::hasTable('hrm_subsidiary_companies')) {
            $company = HrmSubsidiaryCompany::query()->find($run->subsidiary_company_id);
            if ($company && ! empty($company->company_sheba)) {
                $sourceSheba = $company->company_sheba;
            }
        }

        $lines = [];
        $totalAmount = 0.0;
        $validSheba = 0;

        $dateStr = now()->format('Ymd');
        $batchRef = sprintf("%04d%02d-%03d", $run->year, $run->month, $run->id);

        if ($bank === 'satna_rtgs') {
            $lines[] = "SATNA_HEADER:DATE={$dateStr}:BATCH={$batchRef}:SOURCE={$sourceSheba}:CURRENCY=IRR";
        } elseif ($bank === 'melli_bmi') {
            $lines[] = "BMI_PAYROLL_HDR,{$dateStr},{$batchRef}," . count($items) . ",{$sourceSheba}";
        } else {
            $lines[] = "PAYA_ACH_HDR,{$dateStr},{$batchRef}," . count($items) . ",{$sourceSheba}";
        }

        foreach ($items as $item) {
            $emp = $item->employee;
            $profile = $emp?->profile;

            $name = trim(($emp?->first_name . ' ' . $emp?->last_name) ?: '');
            if ($name === '') {
                throw new InvalidArgumentException("نام کارمند در فیش حقوق شناسه #{$item->id} خالی است.");
            }

            $nationalId = trim((string) ($profile?->national_id ?? $emp?->national_id ?? ''));
            if (! \Modules\Hrm\Support\IranianNationalId::isValid($nationalId)) {
                throw new InvalidArgumentException("کد ملی کارمند ({$name}) نامعتبر است.");
            }

            $cleanSheba = trim((string) ($profile?->sheba ?? $emp?->bank_sheba ?? ''));
            if ($cleanSheba === '' || ! $this->standardService->isValidSheba($cleanSheba)) {
                throw new InvalidArgumentException("شماره شبای بانکی کارمند ({$name}) نامعتبر است.");
            }

            $validSheba++;
            $net = (float) $item->net;
            $totalAmount += $net;

            $line = match ($bank) {
                'satna_rtgs' => sprintf("SATNA|%s|%.0f|%s|%s|%s", $cleanSheba, $net, $name, $nationalId, "واریز حقوق دوره {$run->month}"),
                'melli_bmi' => sprintf("%s,%.0f,%s,%s,SALARY_%04d%02d", $cleanSheba, $net, $name, $nationalId, $run->year, $run->month),
                'tejarat_batch' => sprintf("%s*%.0f*%s*%s*%s", $cleanSheba, $net, $name, $nationalId, $batchRef),
                'mellat_batch' => sprintf("%s,%.0f,%s,%s,حقوق ماه %d", $cleanSheba, $net, $name, $nationalId, $run->month),
                default => sprintf("%s,%.0f,%s,%s,%s", $cleanSheba, $net, $name, $nationalId, $batchRef),
            };

            $lines[] = $line;
        }

        $content = implode("\r\n", $lines);

        return [
            'bank' => $bank,
            'source_sheba' => $sourceSheba,
            'record_count' => count($items),
            'total_amount' => $totalAmount,
            'valid_sheba_count' => $validSheba,
            'file_content' => $content,
            'checksum' => hash('sha256', $content),
        ];
    }
}

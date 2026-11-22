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

    /**
     * Generate certified bank export file for any supported Iranian bank or ACH system:
     * - paya_standard (پایا بانک مرکزی)
     * - satna_rtgs (ساتنا مبالغ بالا)
     * - melli_bmi (بانک ملی)
     * - mellat_batch (بانک ملت)
     * - tejarat_batch (بانک تجارت)
     * - sepah_batch (بانک سپه)
     * - saderat_bsi (بانک صادرات)
     *
     * Performs strict pre-export validation on Sheba, Net Payable, National ID, and Names.
     *
     * @return array{
     *   bank: string,
     *   source_sheba: string,
     *   record_count: int,
     *   total_amount: float,
     *   valid_sheba_count: int,
     *   file_content: string,
     *   checksum: string
     * }
     */
    public function generateCertifiedBankFile(HrmPayrollRun $run, string $bank = 'paya_standard', ?string $companySheba = null): array
    {
        $export = match ($bank) {
            'mehr_txt' => $this->standardService->generateMehrTextFile($run, $companySheba),
            'paya_xml' => $this->standardService->generatePayaXml($run, $companySheba),
            'satna_xml' => $this->standardService->generateSatnaXml($run, $companySheba),
            default => $this->standardService->generateBankExport($run, $bank, $companySheba),
        };

        return [
            'bank' => $bank,
            'source_sheba' => $export['source_sheba'],
            'record_count' => $export['total_records'],
            'total_amount' => $export['total_amount'],
            'valid_sheba_count' => $export['total_records'],
            'file_content' => $export['file_content'],
            'checksum' => $export['checksum'],
        ];
    }

    /**
     * Format 1: Direct delegation to Mehr Bank text file generator
     */
    public function generateMehrTextFile(HrmPayrollRun $run, ?string $companySheba = null, ?string $sourceAccount = null): array
    {
        return $this->standardService->generateMehrTextFile($run, $companySheba, $sourceAccount);
    }

    /**
     * Format 2: Direct delegation to Paya ISO 20022 XML generator
     */
    public function generatePayaXml(HrmPayrollRun $run, ?string $companySheba = null): array
    {
        return $this->standardService->generatePayaXml($run, $companySheba);
    }

    /**
     * Format 3: Direct delegation to Satna RTGS XML generator
     */
    public function generateSatnaXml(HrmPayrollRun $run, ?string $companySheba = null): array
    {
        return $this->standardService->generateSatnaXml($run, $companySheba);
    }
}

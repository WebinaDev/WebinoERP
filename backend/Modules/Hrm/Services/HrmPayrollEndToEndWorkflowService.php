<?php

namespace Modules\Hrm\Services;

use Modules\Hrm\Entities\HrmPayrollRun;
use Modules\Hrm\Support\HrmNotifier;

class HrmPayrollEndToEndWorkflowService
{
    public function __construct(
        private readonly IranianPayrollCalculator $calculator,
        private readonly HrmLegalDisketteService $disketteService,
        private readonly HrmStandardBankFormatService $bankService,
        private readonly HrmPayrollAccountingIntegrationService $accountingBridge
    ) {}

    /**
     * Complete lifecycle execution:
     * 1. Recalculate all employee items from decrees and attendance records
     * 2. Pull and settle any retroactive pay adjustments (معوقات)
     * 3. Issue segregated accounting vouchers into General Ledger (دفتر کل)
     * 4. Generate standard legal diskettes (SSO & Tax)
     * 5. Generate Paya bank transfer file
     * 6. Mark payroll run as approved and notify staff
     *
     * @return array<string, mixed>
     */
    public function executeFullPayrollCycle(HrmPayrollRun $run, ?int $userId = null): array
    {
        // 1. Recalculate
        $calcResult = app(\Modules\Hrm\Http\Controllers\PayrollNestedController::class)->runCalculate($run);

        // 2. Issue segregated accounting vouchers
        $voucherResult = $this->accountingBridge->issuePayrollVouchers($run, $userId);

        // 3. Generate legal SSO & Tax diskettes
        $ssoDiskette = $this->disketteService->generateSsoDiskette($run);
        $taxDiskette = $this->disketteService->generateTaxDiskette($run);

        // 4. Generate Paya Bank transfer file
        $payaFile = $this->bankService->generatePayaPaymentFile($run);

        // 5. Update Run Status to Approved
        $run->update([
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        return [
            'status' => 'completed',
            'payroll_run_id' => $run->id,
            'items_count' => $run->items()->count(),
            'total_gross' => (float) $run->items()->sum('gross'),
            'total_net' => (float) $run->items()->sum('net'),
            'accounting_voucher' => $voucherResult,
            'sso_records' => $ssoDiskette['record_count'],
            'tax_records' => $taxDiskette['record_count'],
            'bank_paya_records' => $payaFile['total_records'],
            'message' => 'چرخه کامل حقوق و دستمزد (محاسبه، معوقات، اسناد مالی، دیسکت و حواله بانکی) با موفقیت تکمیل شد.',
        ];
    }
}

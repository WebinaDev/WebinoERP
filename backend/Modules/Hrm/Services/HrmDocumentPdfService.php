<?php

namespace Modules\Hrm\Services;

use App\Services\PdfGeneratorService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Hrm\Entities\HrmDocumentTemplate;
use Modules\Hrm\Entities\HrmEmploymentDecree;
use Modules\Hrm\Entities\HrmPayrollItem;
use Modules\Hrm\Entities\HrmPayrollRun;

class HrmDocumentPdfService
{
    public function __construct(
        private readonly PdfGeneratorService $pdf,
        private readonly HrmSerialService $serials,
        private readonly IranianPayrollCalculator $calc,
    ) {}

    /**
     * @return array{html: string, path: ?string, url: ?string, serial_no: string, pdf_available: bool, message?: string}
     */
    public function payslipPdf(HrmPayrollItem $item, ?string $signerName = null, ?string $signerRole = null): array
    {
        $item->loadMissing(['employee.profile', 'payrollRun']);
        if (! $item->serial_no) {
            $item->serial_no = $this->serials->next('payslip');
            $item->save();
        }
        $html = $this->renderPayslipHtml($item, $signerName, $signerRole);

        return $this->persist('payslips', 'payslip-'.$item->id, $html, (string) $item->serial_no);
    }

    /**
     * @return array{html: string, path: ?string, url: ?string, serial_no: string, pdf_available: bool, message?: string}
     */
    public function decreePdf(HrmEmploymentDecree $decree, ?string $signerName = null, ?string $signerRole = null): array
    {
        $decree->loadMissing('employee');
        if (! $decree->serial_no) {
            $decree->serial_no = $this->serials->next('decree');
        }
        if ($signerName) {
            $decree->signer_name = $signerName;
        }
        if ($signerRole) {
            $decree->signer_role = $signerRole;
        }
        $decree->save();
        $html = $this->renderDecreeHtml($decree);

        return $this->persist('decrees', 'decree-'.$decree->id, $html, (string) $decree->serial_no);
    }

    /**
     * @return array{html: string, path: ?string, url: ?string, serial_no: string, pdf_available: bool, message?: string}
     */
    private function persist(string $folder, string $basename, string $html, string $serial): array
    {
        $binary = $this->pdf->htmlToPdf($html);
        if ($binary === null) {
            return [
                'html' => $html,
                'path' => null,
                'url' => null,
                'serial_no' => $serial,
                'pdf_available' => false,
                'message' => 'PDF unavailable: install barryvdh/laravel-dompdf and PHP ext-dom. HTML returned for print.',
            ];
        }
        $path = $folder.'/'.$basename.'-'.Str::random(6).'.pdf';
        Storage::disk('public')->put($path, $binary);

        return [
            'html' => $html,
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
            'serial_no' => $serial,
            'pdf_available' => true,
        ];
    }

    public function renderPayslipHtml(HrmPayrollItem $item, ?string $signerName = null, ?string $signerRole = null): string
    {
        $template = HrmDocumentTemplate::query()->where('slug', 'payslip')->where('is_active', true)->first();
        $settings = $this->calc->settings();
        $b = $item->breakdown ?? [];
        $lines = $b['lines'] ?? [];
        $emp = $item->employee;
        $run = $item->payrollRun;
        $vars = [
            'employee_name' => trim(($emp->first_name ?? '').' '.($emp->last_name ?? '')),
            'personnel_code' => $emp->employee_code ?? '',
            'period' => ($run->year ?? '').'/'.($run->month ?? ''),
            'engagement_type' => $b['engagement_type'] ?? $emp->engagement_type ?? '',
            'pay_basis' => $b['pay_basis'] ?? $emp->pay_basis ?? '',
            'base' => $b['base'] ?? $emp->base_salary,
            'earnings' => $b['earnings_total'] ?? 0,
            'line_bon' => $lines['bon'] ?? 0,
            'line_housing' => $lines['housing'] ?? 0,
            'line_child' => $lines['child'] ?? 0,
            'line_seniority' => $lines['seniority'] ?? 0,
            'line_reward' => $lines['reward'] ?? 0,
            'line_eydi' => $lines['eydi'] ?? 0,
            'line_bon_card' => $lines['bon_card'] ?? 0,
            'line_marital' => $lines['marital'] ?? 0,
            'line_severance' => $lines['severance'] ?? 0,
            'overtime' => $b['overtime'] ?? 0,
            'mission' => $b['mission'] ?? 0,
            'gross' => $item->gross,
            'insurable' => $b['insurable'] ?? 0,
            'employee_insurance' => $b['employee_insurance'] ?? 0,
            'employer_insurance' => $b['employer_insurance'] ?? 0,
            'unemployment' => $b['unemployment_insurance'] ?? 0,
            'tax' => $b['tax'] ?? 0,
            'loan' => $b['loan_deduction'] ?? 0,
            'advance' => $b['advance_deduction'] ?? 0,
            'other_deductions' => $b['other_deductions'] ?? 0,
            'deductions' => $item->deductions,
            'net' => $item->net,
            'insurance_applicable' => ! empty($b['insurance_applicable']) ? 'بله' : 'خیر',
            'tax_applicable' => ! empty($b['tax_applicable']) ? 'بله' : 'خیر',
            'serial_no' => $item->serial_no ?? '',
            'signer_name' => $signerName ?? '',
            'signer_role' => $signerRole ?? '',
            'stamp_placeholder' => '',
            'law_year_label' => (string) ($settings['law_year_label'] ?? '۱۴۰۵'),
        ];

        if ($template) {
            return $this->replace($template->html, $vars);
        }

        return '<div dir="rtl"><h2>فیش حقوقی</h2><pre>'.e(json_encode($vars, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)).'</pre></div>';
    }

    public function renderDecreeHtml(HrmEmploymentDecree $decree): string
    {
        $template = HrmDocumentTemplate::query()->where('slug', 'decree')->where('is_active', true)->first();
        $settings = $this->calc->settings();
        $emp = $decree->employee;
        $benefitLines = '';
        foreach (($decree->benefits ?? []) as $benefit) {
            if (! is_array($benefit)) {
                continue;
            }
            $benefitLines .= ($benefit['name'] ?? $benefit['code'] ?? '').': '.($benefit['amount'] ?? 0)."\n";
        }
        $vars = [
            'decree_no' => $decree->decree_no,
            'employee_name' => trim(($emp->first_name ?? '').' '.($emp->last_name ?? '')),
            'personnel_code' => $emp->employee_code ?? '',
            'job_title' => $decree->job_title,
            'department' => $decree->department,
            'contract_type' => $decree->contract_type,
            'engagement_type' => $decree->engagement_type,
            'pay_basis' => $decree->pay_basis,
            'effective_from' => optional($decree->effective_from)?->toDateString(),
            'effective_to' => optional($decree->effective_to)?->toDateString(),
            'base_salary' => $decree->base_salary,
            'daily_wage' => $decree->daily_wage,
            'hourly_rate' => $decree->hourly_rate,
            'project_fee' => $decree->project_fee,
            'insurance_applicable' => $decree->insurance_applicable ? 'بله' : 'خیر',
            'tax_applicable' => $decree->tax_applicable ? 'بله' : 'خیر',
            'benefits_rows' => $benefitLines !== '' ? $benefitLines : '—',
            'serial_no' => $decree->serial_no ?? '',
            'signer_name' => $decree->signer_name ?? '',
            'signer_role' => $decree->signer_role ?? '',
            'stamp_placeholder' => $decree->stamp_path ? '<img src="'.e($decree->stamp_path).'" alt="stamp" style="max-height:64px">' : '',
            'law_year_label' => (string) ($settings['law_year_label'] ?? '۱۴۰۵'),
        ];

        if ($template) {
            return $this->replace($template->html, $vars);
        }

        return '<div dir="rtl"><h2>حکم کارگزینی</h2><pre>'.e(json_encode($vars, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)).'</pre></div>';
    }

    /**
     * @param  array<string, mixed>  $vars
     */
    private function replace(string $html, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $html = str_replace('{{'.$key.'}}', e((string) ($value ?? '')), $html);
        }

        return $html;
    }
}

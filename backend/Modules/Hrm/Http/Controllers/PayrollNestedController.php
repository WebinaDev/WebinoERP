<?php

namespace Modules\Hrm\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Hrm\Entities\HrmDocumentTemplate;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmEmploymentDecree;
use Modules\Hrm\Entities\HrmLoan;
use Modules\Hrm\Entities\HrmEmployeeSalary;
use Modules\Hrm\Entities\HrmPayrollComponent;
use Modules\Hrm\Entities\HrmPayrollItem;
use Modules\Hrm\Entities\HrmPayrollRun;
use Modules\Hrm\Entities\HrmPayrollSetting;
use Modules\Hrm\Services\HrmCompensationRules;
use Modules\Hrm\Services\IranianPayrollCalculator;
use Modules\Hrm\Support\HrmNotifier;
use Modules\Hrm\Services\HrmSerialService;
use Modules\Hrm\Services\HrmPayrollAccountingBridge;
use Modules\Hrm\Services\HrmInsuranceListService;
use Modules\Hrm\Services\HrmBankExportService;
use Modules\Hrm\Services\HrmDocumentPdfService;
use Illuminate\Support\Facades\Response;

class PayrollNestedController extends Controller
{
    use PaginatesApi;

    public function settingsGet(): JsonResponse
    {
        $stored = [];
        foreach (HrmPayrollSetting::query()->get() as $row) {
            $stored[$row->key] = $row->value;
        }
        $settings = array_replace(app(IranianPayrollCalculator::class)->defaults(), $stored);

        return response()->json(['data' => $settings]);
    }

    public function settingsSave(Request $request): JsonResponse
    {
        $data = $request->validate(['settings' => 'required|array']);
        foreach ($data['settings'] as $key => $value) {
            HrmPayrollSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        return response()->json(['message' => 'Settings saved']);
    }

    public function componentsIndex(Request $request): JsonResponse
    {
        return $this->paginatedResponse(HrmPayrollComponent::query()->orderBy('name')->paginate($this->perPage($request)));
    }

    public function componentsStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'code' => 'nullable|string|max:40',
            'type' => 'required|in:earning,deduction',
            'calculation' => 'nullable|string|max:40',
            'default_amount' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
            'is_insurable' => 'nullable|boolean',
            'is_taxable' => 'nullable|boolean',
        ]);
        $component = HrmPayrollComponent::create($data);

        return response()->json(['data' => $component, 'message' => 'Component saved'], 201);
    }

    public function employeeSalariesGet(Request $request): JsonResponse
    {
        $query = HrmEmployeeSalary::query()->with('employee')->orderByDesc('effective_from');
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        return $this->paginatedResponse($query->paginate($this->perPage($request)));
    }

    public function employeeSalariesSave(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:hrm_employees,id',
            'base_salary' => 'required|numeric|min:0',
            'components' => 'nullable|array',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date',
        ]);
        $employee = HrmEmployee::query()->findOrFail($data['employee_id']);
        $employee->base_salary = $data['base_salary'];
        app(HrmCompensationRules::class)->assertEmployee($employee);
        $employee->save();
        $salary = HrmEmployeeSalary::create($data);

        return response()->json(['data' => $salary->load('employee'), 'message' => 'Salary saved'], 201);
    }

    public function runsIndex(Request $request): JsonResponse
    {
        return $this->paginatedResponse(HrmPayrollRun::query()->orderByDesc('year')->orderByDesc('month')->paginate($this->perPage($request)));
    }

    public function runStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'year' => 'required|integer|min:1300|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);
        $data['created_by'] = $request->user()->id;
        $run = HrmPayrollRun::create($data);

        return response()->json(['data' => $run, 'message' => 'Run created'], 201);
    }

    public function runGet(HrmPayrollRun $run): JsonResponse
    {
        $run->load(['items.employee']);

        return response()->json(['data' => $run]);
    }

    public function runCalculate(HrmPayrollRun $run): JsonResponse
    {
        $calc = app(IranianPayrollCalculator::class);
        DB::transaction(function () use ($run, $calc) {
            $calc->resetRun($run->id);
            $employees = HrmEmployee::query()->where('status', 'active')
                ->when($run->employee_id, fn ($q) => $q->whereKey($run->employee_id))
                ->get();
            $total = 0;
            foreach ($employees as $employee) {
                $result = $calc->forEmployee($employee, (int) $run->year, (int) $run->month, $run->id);
                $payload = [
                    'gross' => $result['gross'],
                    'deductions' => $result['deductions'],
                    'net' => $result['net'],
                ];
                if (Schema::hasColumn('hrm_payroll_items', 'breakdown')) {
                    $payload['breakdown'] = $result['breakdown'];
                }
                HrmPayrollItem::query()->updateOrCreate(
                    ['payroll_run_id' => $run->id, 'employee_id' => $employee->id],
                    $payload
                );
                $total += $result['net'];
            }
            $run->update(['status' => 'calculated', 'total_amount' => $total]);
        });

        return response()->json(['data' => $run->fresh('items.employee'), 'message' => 'Calculated']);
    }

    public function runApprove(HrmPayrollRun $run): JsonResponse
    {
        $run->update(['status' => 'approved']);
        $accounting = app(HrmPayrollAccountingBridge::class)->postPayrollRun($run, request()->user()?->id);
        $run->load('items.employee');
        foreach ($run->items as $item) {
            if (! $item->serial_no) {
                $item->serial_no = app(HrmSerialService::class)->next('payslip');
                $item->save();
            }
            $name = trim(($item->employee->first_name ?? '').' '.($item->employee->last_name ?? ''));
            HrmNotifier::notify(
                $item->employee_id,
                'salary_paid',
                'واریز حقوق',
                'حقوق دوره '.$run->title.' (run:'.$run->id.') برای '.$name.' به مبلغ '.$item->net.' تأیید شد.'
            );
        }

        return response()->json([
            'data' => $run->fresh('items.employee'),
            'accounting' => $accounting,
            'message' => 'Approved',
        ]);
    }

    public function runMarkPaid(HrmPayrollRun $run): JsonResponse
    {
        $accounting = app(HrmPayrollAccountingBridge::class)->postPayrollRun($run, request()->user()?->id);
        $run->update(['status' => 'paid', 'paid_at' => now()]);
        $run->load('items.employee');
        foreach ($run->items as $item) {
            HrmNotifier::notify(
                $item->employee_id,
                'payroll_paid',
                'پرداخت حقوق',
                'حقوق دوره '.$run->title.' پرداخت شد. خالص: '.$item->net
            );
        }

        return response()->json([
            'data' => $run->fresh('items.employee'),
            'accounting' => $accounting,
            'message' => 'Marked paid',
        ]);
    }

    public function payslipPdf(Request $request, HrmPayrollItem $item): JsonResponse
    {
        $data = $request->validate([
            'signer_name' => 'nullable|string|max:150',
            'signer_role' => 'nullable|string|max:100',
        ]);
        $result = app(HrmDocumentPdfService::class)->payslipPdf(
            $item,
            $data['signer_name'] ?? null,
            $data['signer_role'] ?? null
        );

        return response()->json(['data' => $result]);
    }

    public function decreePdf(Request $request, HrmEmploymentDecree $decree): JsonResponse
    {
        $data = $request->validate([
            'signer_name' => 'nullable|string|max:150',
            'signer_role' => 'nullable|string|max:100',
        ]);
        $result = app(HrmDocumentPdfService::class)->decreePdf(
            $decree,
            $data['signer_name'] ?? $decree->signer_name,
            $data['signer_role'] ?? $decree->signer_role
        );

        return response()->json(['data' => $result]);
    }

    public function insuranceList(Request $request, HrmPayrollRun $run)
    {
        $workshopId = $request->filled('workshop_id') ? $request->integer('workshop_id') : null;
        $format = $request->string('format', 'json')->toString();
        $rows = app(HrmInsuranceListService::class)->rows($run, $workshopId);
        if ($format === 'csv') {
            $csv = app(HrmInsuranceListService::class)->toCsv($rows);

            return Response::make($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="sso-list-run-'.$run->id.'.csv"',
            ]);
        }

        if (in_array($format, ['txt', 'diskette'], true)) {
            $txt = app(HrmInsuranceListService::class)->toDiskette($run, $rows);

            return Response::make($txt, 200, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="DSKWOR-run-'.$run->id.'.txt"',
            ]);
        }
        if ($format === 'html') {
            $html = '<div dir="rtl"><h2>لیست بیمه تأمین اجتماعی — '.$run->title.'</h2><table border="1" cellpadding="4"><tr>';
            foreach (['کد', 'نام', 'نام خانوادگی', 'کدملی', 'شماره بیمه', 'کارگاه', 'مشمول', 'سهم کارگر', 'سهم کارفرما', 'بیکاری'] as $h) {
                $html .= '<th>'.$h.'</th>';
            }
            $html .= '</tr>';
            foreach ($rows as $r) {
                $html .= '<tr>'
                    .'<td>'.e((string) ($r['employee_code'] ?? '')).'</td>'
                    .'<td>'.e((string) ($r['first_name'] ?? '')).'</td>'
                    .'<td>'.e((string) ($r['last_name'] ?? '')).'</td>'
                    .'<td>'.e((string) ($r['national_id'] ?? '')).'</td>'
                    .'<td>'.e((string) ($r['insurance_no'] ?? '')).'</td>'
                    .'<td>'.e((string) ($r['workshop_id'] ?? '')).'</td>'
                    .'<td>'.e((string) ($r['insurable'] ?? '')).'</td>'
                    .'<td>'.e((string) ($r['employee_insurance'] ?? '')).'</td>'
                    .'<td>'.e((string) ($r['employer_insurance'] ?? '')).'</td>'
                    .'<td>'.e((string) ($r['unemployment_insurance'] ?? '')).'</td>'
                    .'</tr>';
            }
            $html .= '</table></div>';

            return Response::make($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        return response()->json(['data' => ['rows' => $rows, 'count' => count($rows)]]);
    }

    public function severancePreview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:hrm_employees,id',
            'base_salary' => 'nullable|numeric|min:0',
        ]);
        $employee = HrmEmployee::query()->findOrFail($data['employee_id']);
        $calc = app(IranianPayrollCalculator::class);
        $settings = $calc->settings();
        $base = array_key_exists('base_salary', $data) ? (float) $data['base_salary'] : (float) ($employee->base_salary ?? 0);
        $severance = $calc->severanceAmount($employee, $base, $settings);
        $eydi = $calc->eydiAmount($base, $settings, $employee);

        return response()->json([
            'data' => [
                'law_year' => $settings['law_year'] ?? 1405,
                'law_year_label' => $settings['law_year_label'] ?? '۱۴۰۵',
                'base' => $base,
                'severance' => $severance,
                'eydi_if_december' => $eydi,
                'notes' => 'سنوات پایان خدمت = مزد ماهانه × سال سابقه (تناسب ماه). عیدی = ۲×مزد با سقف ۳×حداقل ماهانه و تناسب ماه کارکرد.',
            ],
        ]);
    }


    public function payslipIssue(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:hrm_employees,id',
            'year' => 'required|integer|min:1300|max:2100',
            'month' => 'required|integer|min:1|max:12',
            'title' => 'nullable|string|max:200',
        ]);
        $employee = HrmEmployee::query()->findOrFail($data['employee_id']);
        app(HrmCompensationRules::class)->assertEmployee($employee);

        $run = HrmPayrollRun::query()->updateOrCreate(
            [
                'employee_id' => $employee->id,
                'year' => $data['year'],
                'month' => $data['month'],
            ],
            [
                'title' => $data['title'] ?? ('فیش '.$employee->employee_code.' '.$data['year'].'/'.$data['month']),
                'status' => 'draft',
                'created_by' => $request->user()->id,
            ]
        );

        $calc = app(IranianPayrollCalculator::class);
        DB::transaction(function () use ($run, $calc, $employee) {
            $calc->resetRun($run->id);
            $result = $calc->forEmployee($employee, (int) $run->year, (int) $run->month, $run->id);
            $payload = [
                'gross' => $result['gross'],
                'deductions' => $result['deductions'],
                'net' => $result['net'],
            ];
            if (Schema::hasColumn('hrm_payroll_items', 'breakdown')) {
                $payload['breakdown'] = $result['breakdown'];
            }
            HrmPayrollItem::query()->updateOrCreate(
                ['payroll_run_id' => $run->id, 'employee_id' => $employee->id],
                $payload
            );
            $run->update(['status' => 'calculated', 'total_amount' => $result['net']]);
        });

        $item = HrmPayrollItem::query()->where('payroll_run_id', $run->id)->where('employee_id', $employee->id)->first();
        $html = $this->renderPayslipHtml($item);

        return response()->json([
            'data' => [
                'run' => $run->fresh(),
                'item' => $item,
                'html' => $html,
            ],
            'message' => 'Payslip issued',
        ], 201);
    }

    public function loansIndex(Request $request): JsonResponse
    {
        $query = HrmLoan::query()->with('employee')->orderByDesc('id');
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }
        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        return $this->paginatedResponse($query->paginate($this->perPage($request)));
    }

    public function loansStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:hrm_employees,id',
            'type' => 'required|in:loan,advance',
            'title' => 'nullable|string|max:200',
            'principal' => 'required|numeric|min:1',
            'installment_amount' => 'required|numeric|min:1',
            'start_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);
        $principal = (float) $data['principal'];
        $installment = (float) $data['installment_amount'];
        $data['remaining_balance'] = $principal;
        $data['installments_total'] = (int) max(1, ceil($principal / $installment));
        $data['installments_paid'] = 0;
        $data['status'] = 'active';
        $loan = HrmLoan::query()->create($data);

        return response()->json(['data' => $loan->load('employee'), 'message' => 'Loan saved'], 201);
    }

    public function loansSettle(HrmLoan $hrmLoan): JsonResponse
    {
        $hrmLoan->update(['status' => 'settled', 'remaining_balance' => 0]);

        return response()->json(['data' => $hrmLoan->fresh(), 'message' => 'Settled']);
    }

    public function templatesIndex(): JsonResponse
    {
        $rows = HrmDocumentTemplate::query()->orderBy('slug')->get();

        return response()->json(['data' => $rows]);
    }

    public function templatesSave(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slug' => 'required|in:payslip,decree',
            'name' => 'required|string|max:150',
            'html' => 'required|string|max:200000',
            'is_active' => 'nullable|boolean',
        ]);
        $template = HrmDocumentTemplate::query()->updateOrCreate(
            ['slug' => $data['slug']],
            $data
        );

        return response()->json(['data' => $template, 'message' => 'Template saved']);
    }

    public function templatesRender(Request $request, string $slug): JsonResponse
    {
        $template = HrmDocumentTemplate::query()->where('slug', $slug)->where('is_active', true)->firstOrFail();
        $vars = [];
        if ($slug === 'payslip') {
            $item = HrmPayrollItem::query()->with(['employee.profile', 'payrollRun'])->findOrFail($request->integer('payroll_item_id'));
            $b = $item->breakdown ?? [];
            $lines = $b['lines'] ?? [];
            $emp = $item->employee;
            $vars = [
                'employee_name' => trim(($emp->first_name ?? '').' '.($emp->last_name ?? '')),
                'personnel_code' => $emp->employee_code ?? '',
                'period' => ($item->payrollRun->year ?? '').'/'.($item->payrollRun->month ?? ''),
                'engagement_type' => $this->engagementLabel($b['engagement_type'] ?? $emp->engagement_type),
                'pay_basis' => $this->payBasisLabel($b['pay_basis'] ?? $emp->pay_basis),
                'base' => $b['base'] ?? $emp->base_salary,
                'earnings' => $b['earnings_total'] ?? 0,
                'line_bon' => $lines['bon'] ?? 0,
                'line_housing' => $lines['housing'] ?? 0,
                'line_child' => $lines['child'] ?? 0,
                'line_seniority' => $lines['seniority'] ?? 0,
                'line_reward' => $lines['reward'] ?? 0,
                'line_eydi' => $lines['eydi'] ?? 0,
                'line_bon_card' => $lines['bon_card'] ?? 0,
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
                'signer_name' => '',
                'signer_role' => '',
                'stamp_placeholder' => '',
                'law_year_label' => (string) (app(IranianPayrollCalculator::class)->settings()['law_year_label'] ?? '۱۴۰۵'),
            ];
        } else {
            $decree = HrmEmploymentDecree::query()->with('employee')->findOrFail($request->integer('decree_id'));
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
                'engagement_type' => $this->engagementLabel($decree->engagement_type),
                'pay_basis' => $this->payBasisLabel($decree->pay_basis),
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
                'stamp_placeholder' => '',
                'law_year_label' => (string) (app(IranianPayrollCalculator::class)->settings()['law_year_label'] ?? '۱۴۰۵'),
            ];
        }
        $html = $template->html;
        foreach ($vars as $key => $value) {
            $html = str_replace('{{'.$key.'}}', e((string) ($value ?? '')), $html);
        }

        return response()->json(['data' => ['html' => $html, 'slug' => $slug]]);
    }

    public function payslipsList(HrmPayrollRun $run): JsonResponse
    {
        $items = $run->items()->with('employee')->get();

        return response()->json(['data' => $items]);
    }


    protected function engagementLabel(?string $code): string
    {
        return [
            'full_time' => 'تمام‌وقت',
            'part_time' => 'پاره‌وقت',
            'contractor' => 'پیمانکاری',
            'freelance' => 'فریلنس',
            'project' => 'پروژه‌ای',
            'remote' => 'دورکاری',
        ][$code ?? ''] ?? (string) $code;
    }

    protected function payBasisLabel(?string $code): string
    {
        return [
            'monthly' => 'ماهانه',
            'daily' => 'روزانه',
            'hourly' => 'ساعتی',
            'project_fee' => 'حق‌الزحمه پروژه',
        ][$code ?? ''] ?? (string) $code;
    }

    protected function renderPayslipHtml(?HrmPayrollItem $item): string
    {
        if (! $item) {
            return '';
        }
        $template = HrmDocumentTemplate::query()->where('slug', 'payslip')->where('is_active', true)->first();
        if (! $template) {
            return '';
        }
        $fake = Request::create('/', 'GET', ['payroll_item_id' => $item->id]);
        $payload = $this->templatesRender($fake, 'payslip')->getData(true);

        return (string) ($payload['data']['html'] ?? '');
    }


    public function bankExport(Request $request, HrmPayrollRun $run)
    {
        $channel = $request->string('channel', 'paya')->toString();
        $rows = app(HrmBankExportService::class)->rows($run, $channel);
        if ($request->string('format', 'csv')->toString() === 'json') {
            return response()->json(['data' => ['rows' => $rows, 'count' => count($rows), 'channel' => $channel]]);
        }
        $csv = app(HrmBankExportService::class)->toCsv($rows);

        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="salary-'.$channel.'-run-'.$run->id.'.csv"',
        ]);
    }

}

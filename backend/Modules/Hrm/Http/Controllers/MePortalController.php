<?php

namespace Modules\Hrm\Http\Controllers;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\Hrm\Entities\HrmAttendanceRecord;
use Modules\Hrm\Entities\HrmDependent;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmEmployeeProfile;
use Modules\Hrm\Entities\HrmEmploymentDecree;
use Modules\Hrm\Entities\HrmLoan;
use Modules\Hrm\Entities\HrmMissionOrder;
use Modules\Hrm\Entities\HrmNotice;
use Modules\Hrm\Entities\HrmOrgPosition;
use Modules\Hrm\Entities\HrmPayrollItem;
use Modules\Hrm\Entities\HrmPersonnelDocument;
use Modules\Hrm\Entities\HrmRequest;
use Modules\Hrm\Entities\HrmPayrollComponent;
use Modules\Hrm\Services\HrmCompensationRules;
use Modules\Hrm\Services\HrmApprovalWorkflow;
use Modules\Hrm\Support\HrmNotifier;

class MePortalController extends Controller
{
    protected function resolveEmployee(): ?HrmEmployee
    {
        $userId = auth()->id();
        if (! $userId) {
            return null;
        }

        $employee = HrmEmployee::query()->where('user_id', $userId)->first();
        if ($employee) {
            return $employee;
        }
        $email = auth()->user()?->email;
        if (! $email) {
            return null;
        }

        return HrmEmployee::query()->where('email', $email)->first();
    }

    public function me(): JsonResponse
    {
        $employee = $this->resolveEmployee();
        if (! $employee) {
            return response()->json(['data' => ['employee' => null]]);
        }

        $employee->load(['profile', 'shiftTemplate']);

        return response()->json([
            'data' => [
                'employee' => [
                    'id' => $employee->id,
                    'first_name' => $employee->first_name,
                    'last_name' => $employee->last_name,
                    'email' => $employee->email,
                    'phone' => $employee->phone,
                    'employee_code' => $employee->employee_code,
                    'department' => $employee->department,
                    'position' => $employee->position,
                    'hire_date' => optional($employee->hire_date)?->toDateString(),
                    'base_salary' => (float) $employee->base_salary,
                    'contract_type' => $employee->contract_type,
                    'engagement_type' => $employee->engagement_type,
                    'pay_basis' => $employee->pay_basis,
                    'status' => $employee->is_active ? 'active' : 'inactive',
                    'national_id' => $employee->profile?->national_id,
                    'iban' => $employee->profile?->sheba,
                    'avatar_url' => $employee->profile?->avatar_url,
                    'birth_date' => optional($employee->profile?->birth_date)?->toDateString(),
                    'gender' => $employee->profile?->gender,
                    'marital_status' => $employee->profile?->marital_status,
                    'children_count' => $employee->profile?->children_count,
                    'address' => $employee->profile?->address,
                    'emergency_contact' => $employee->profile?->emergency_contact,
                ],
            ],
        ]);
    }

    public function notices(): JsonResponse
    {
        if (! Schema::hasTable('hrm_notices')) {
            return response()->json(['data' => ['notices' => []]]);
        }

        $today = now()->toDateString();
        $notices = HrmNotice::query()
            ->where('is_active', true)
            ->where(function ($q) use ($today) {
                $q->whereNull('date_from')->orWhere('date_from', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('date_to')->orWhere('date_to', '>=', $today);
            })
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'title', 'body', 'date_from', 'date_to', 'created_at']);

        return response()->json(['data' => ['notices' => $notices]]);
    }

    public function attendanceIndex(Request $request): JsonResponse
    {
        $employee = $this->resolveEmployee();
        if (! $employee) {
            return response()->json(['data' => ['items' => [], 'total' => 0]]);
        }

        $query = HrmAttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->orderByDesc('date');

        if ($request->filled('date_from')) {
            $query->where('date', '>=', $request->string('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('date', '<=', $request->string('date_to'));
        }

        $items = $query->limit(62)->get()->map(fn (HrmAttendanceRecord $a) => [
            'id' => $a->id,
            'work_date' => optional($a->date)?->toDateString() ?? (string) $a->date,
            'check_in' => $a->check_in,
            'check_out' => $a->check_out,
            'status' => $a->status,
            'notes' => $a->notes,
        ])->values()->all();

        return response()->json(['data' => ['items' => $items, 'total' => count($items)]]);
    }

    public function attendancePunch(Request $request): JsonResponse
    {
        $employee = $this->resolveEmployee();
        if (! $employee) {
            return response()->json(['message' => __('messages.no_employee_profile_linked_to_this_user')], 404);
        }

        $data = $request->validate([
            'action' => 'nullable|string|max:32',
            'type' => 'nullable|string|max:32',
            'notes' => 'nullable|string|max:500',
        ]);

        $today = now()->toDateString();
        $time = now()->format('H:i:s');
        $record = HrmAttendanceRecord::query()->firstOrNew([
            'employee_id' => $employee->id,
            'date' => $today,
        ]);

        $action = $data['action'] ?? $data['type'] ?? ($record->check_in ? 'out' : 'in');
        if ($action === 'out' || ($record->check_in && ! $record->check_out)) {
            $record->check_out = $time;
            $message = 'خروج شما با موفقیت ثبت شد.';
        } else {
            $record->check_in = $time;
            $record->status = 'present';
            $message = 'ورود شما با موفقیت ثبت شد.';
        }

        if (! empty($data['notes'])) {
            $record->notes = $data['notes'];
        }
        $record->save();

        return response()->json([
            'data' => [
                'id' => $record->id,
                'work_date' => $today,
                'check_in' => $record->check_in,
                'check_out' => $record->check_out,
                'status' => $record->status,
            ],
            'message' => $message,
        ]);
    }

    public function decrees(): JsonResponse
    {
        $employee = $this->resolveEmployee();
        if (! $employee || ! Schema::hasTable('hrm_employment_decrees')) {
            return response()->json(['data' => ['decrees' => []]]);
        }

        $decrees = HrmEmploymentDecree::query()
            ->where('employee_id', $employee->id)
            ->orderByDesc('effective_from')
            ->get()
            ->map(fn (HrmEmploymentDecree $d) => [
                'id' => $d->id,
                'decree_no' => $d->decree_no,
                'decree_type' => $d->decree_type,
                'status' => $d->status,
                'job_title' => $d->job_title ?: $employee->position,
                'department' => $d->department ?: ($employee->department?->name ?? 'عمومی'),
                'base_salary' => (float) $d->base_salary,
                'daily_wage' => (float) $d->daily_wage,
                'contract_type' => $d->contract_type,
                'engagement_type' => $d->engagement_type,
                'effective_from' => optional($d->effective_from)?->toDateString(),
                'effective_to' => optional($d->effective_to)?->toDateString(),
                'notes' => $d->notes,
            ])
            ->values()
            ->all();

        return response()->json(['data' => ['decrees' => $decrees]]);
    }

    public function dependentsIndex(): JsonResponse
    {
        $employee = $this->resolveEmployee();
        if (! $employee || ! Schema::hasTable('hrm_dependents')) {
            return response()->json(['data' => ['dependents' => []]]);
        }

        $dependents = HrmDependent::query()
            ->where('employee_id', $employee->id)
            ->orderBy('id')
            ->get()
            ->map(fn (HrmDependent $d) => [
                'id' => $d->id,
                'full_name' => $d->full_name,
                'relation' => $d->relation,
                'national_id' => $d->national_id,
                'birth_date' => optional($d->birth_date)?->toDateString(),
            ])
            ->values()
            ->all();

        return response()->json(['data' => ['dependents' => $dependents]]);
    }

    public function dependentsStore(Request $request): JsonResponse
    {
        $employee = $this->resolveEmployee();
        if (! $employee) {
            return response()->json(['message' => __('messages.no_employee_profile_linked_to_this_user')], 404);
        }

        $data = $request->validate([
            'full_name' => 'required|string|max:150',
            'relation' => 'required|string|max:50',
            'national_id' => 'nullable|string|max:20',
            'birth_date' => 'nullable|date',
        ]);

        $dep = HrmDependent::query()->create(array_merge($data, ['employee_id' => $employee->id]));

        return response()->json(['data' => $dep, 'message' => __('messages.saved')], 201);
    }

    public function orgChart(): JsonResponse
    {
        if (! Schema::hasTable('hrm_org_positions')) {
            return response()->json(['data' => ['positions' => []]]);
        }

        $positions = HrmOrgPosition::query()
            ->with(['department:id,name', 'reportsTo:id,title'])
            ->orderBy('id')
            ->get()
            ->map(fn (HrmOrgPosition $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'department' => $p->department?->name,
                'reports_to_id' => $p->reports_to_id,
                'reports_to_title' => $p->reportsTo?->title,
                'job_grade' => $p->job_grade,
                'status' => $p->status,
            ]);

        return response()->json(['data' => ['positions' => $positions]]);
    }

    public function profileShow(): JsonResponse
    {
        $employee = $this->resolveEmployee();
        if (! $employee) {
            return response()->json(['message' => __('messages.no_employee_profile_linked_to_this_user')], 404);
        }

        $profile = $employee->profile;

        return response()->json([
            'data' => [
                'employee' => $employee,
                'profile' => $profile,
            ],
        ]);
    }

    public function profileUpdate(Request $request): JsonResponse
    {
        $employee = $this->resolveEmployee();
        if (! $employee) {
            return response()->json(['message' => __('messages.no_employee_profile_linked_to_this_user')], 404);
        }

        $data = $request->validate([
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'emergency_contact' => 'nullable|string|max:100',
            'sheba' => 'nullable|string|max:34',
        ]);

        if (isset($data['phone'])) {
            $employee->update(['phone' => $data['phone']]);
        }

        $profileData = array_intersect_key($data, array_flip(['address', 'emergency_contact', 'sheba']));
        if (! empty($profileData)) {
            HrmEmployeeProfile::query()->updateOrCreate(
                ['employee_id' => $employee->id],
                $profileData
            );
        }

        return response()->json(['message' => __('messages.profile_updated')]);
    }

    public function myPayslips(): JsonResponse
    {
        $employee = $this->resolveEmployee();
        if (! $employee || ! Schema::hasTable('hrm_payroll_items')) {
            return response()->json(['data' => ['payslips' => []]]);
        }

        $payslips = HrmPayrollItem::query()
            ->with('payrollRun')
            ->where('employee_id', $employee->id)
            ->orderByDesc('id')
            ->limit(48)
            ->get()
            ->map(function (HrmPayrollItem $item) {
                $run = $item->payrollRun;
                $bd = is_array($item->breakdown) ? $item->breakdown : [];

                return [
                    'id' => $item->id,
                    'run_title' => $run?->title ?? '',
                    'jalali_year' => $run?->year ?? 0,
                    'jalali_month' => $run?->month ?? 0,
                    'gross' => (float) $item->gross,
                    'net' => (float) $item->net,
                    'deductions' => (float) $item->deductions,
                    'base_salary' => (float) ($bd['base'] ?? 0),
                    'overtime' => (float) ($bd['overtime'] ?? 0),
                    'night_allowance' => (float) ($bd['night_allowance'] ?? 0),
                    'holiday_allowance' => (float) ($bd['holiday_allowance'] ?? 0),
                    'shift_allowance' => (float) ($bd['shift_allowance'] ?? 0),
                    'mission' => (float) ($bd['mission'] ?? 0),
                    'retro_current_year' => (float) ($bd['retro_current_year'] ?? 0),
                    'retro_prior_years' => (float) ($bd['retro_prior_years'] ?? 0),
                    'employee_insurance' => (float) ($item->employee_insurance ?: ($bd['employee_insurance'] ?? 0)),
                    'employer_insurance' => (float) ($item->employer_insurance ?: ($bd['employer_insurance'] ?? 0)),
                    'tax' => (float) ($item->tax ?: ($bd['tax'] ?? 0)),
                    'loan_deduction' => (float) ($bd['loan_deduction'] ?? 0),
                    'advance_deduction' => (float) ($bd['advance_deduction'] ?? 0),
                    'breakdown' => $bd,
                ];
            })
            ->values()
            ->all();

        return response()->json(['data' => ['payslips' => $payslips]]);
    }

    public function myRequests(): JsonResponse
    {
        $employee = $this->resolveEmployee();
        if (! $employee || ! Schema::hasTable('hrm_requests')) {
            return response()->json(['data' => ['requests' => []]]);
        }

        $rows = HrmRequest::query()
            ->where('employee_id', $employee->id)
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (HrmRequest $r) => $this->formatRequest($r));

        return response()->json(['data' => ['requests' => $rows]]);
    }

    public function myRequestsStore(Request $request): JsonResponse
    {
        $employee = $this->resolveEmployee();
        if (! $employee) {
            return response()->json(['message' => __('messages.no_employee_profile_linked_to_this_user')], 404);
        }

        $data = $request->validate([
            'type' => 'required|in:overtime,mission,remote_work,remote,loan,advance,leave',
            'notes' => 'nullable|string|max:2000',
            'payload' => 'nullable|array',
            'payload.hours' => 'nullable|numeric|min:0',
            'payload.days' => 'nullable|numeric|min:0',
            'payload.amount' => 'nullable|numeric|min:0',
            'payload.installments' => 'nullable|integer|min:1|max:60',
            'payload.date' => 'nullable|date',
            'payload.start_date' => 'nullable|date',
            'payload.end_date' => 'nullable|date',
            'payload.starts_at' => 'nullable|date',
            'payload.ends_at' => 'nullable|date',
            'payload.destination' => 'nullable|string|max:200',
            'payload.year' => 'nullable|integer',
            'payload.month' => 'nullable|integer|min:1|max:12',
            'payload.location' => 'nullable|string|max:200',
        ]);

        $wf = app(HrmApprovalWorkflow::class);
        $boot = Schema::hasColumn('hrm_requests', 'approval_step')
            ? $wf->bootstrapFields($data['type'])
            : ['status' => 'pending_manager'];

        $row = HrmRequest::query()->create(array_merge([
            'employee_id' => $employee->id,
            'user_id' => auth()->id(),
            'type' => $data['type'],
            'payload' => $data['payload'] ?? [],
            'notes' => $data['notes'] ?? null,
        ], $boot));

        // Real integration with Loans table if request is loan/advance and pre-approved or directly requested
        if (in_array($data['type'], ['loan', 'advance'], true) && Schema::hasTable('hrm_loans')) {
            $amount = (float) ($data['payload']['amount'] ?? 0);
            $installments = max(1, (int) ($data['payload']['installments'] ?? 10));
            if ($amount > 0) {
                HrmLoan::query()->create([
                    'employee_id' => $employee->id,
                    'type' => $data['type'] === 'advance' ? 'advance' : 'loan',
                    'title' => ($data['type'] === 'advance' ? 'مساعده حقوق' : 'وام پرسنلی') . ' - ثبت خودکار پرتال',
                    'principal' => $amount,
                    'installment_amount' => round($amount / $installments, 2),
                    'remaining_balance' => $amount,
                    'installments_total' => $installments,
                    'installments_paid' => 0,
                    'status' => 'active',
                ]);
            }
        }

        // Real integration with Mission Orders table if request is mission
        if ($data['type'] === 'mission' && Schema::hasTable('hrm_mission_orders')) {
            $startsAt = $data['payload']['starts_at'] ?? $data['payload']['date'] ?? now()->toDateTimeString();
            $endsAt = $data['payload']['ends_at'] ?? Carbon::parse($startsAt)->addDays(max(1, (int) ($data['payload']['days'] ?? 1)))->toDateTimeString();
            $dest = $data['payload']['destination'] ?? $data['payload']['location'] ?? 'ماموریت اداری';

            HrmMissionOrder::query()->create([
                'employee_id' => $employee->id,
                'mission_no' => 'MIS-REQ-' . $row->id . '-' . rand(100, 999),
                'mission_type' => 'daily',
                'destination' => $dest,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'per_diem_rate' => (float) ($data['payload']['amount'] ?? 0),
                'status' => 'hr_approved',
                'mission_report' => $data['notes'] ?? null,
            ]);
        }

        return response()->json(['data' => $this->formatRequest($row), 'message' => __('messages.request_submitted')], 201);
    }

    public function requestApprove(Request $request, HrmRequest $hrmRequest): JsonResponse
    {
        $wf = app(HrmApprovalWorkflow::class);
        $status = $wf->approveModel($hrmRequest, $request->user());
        $hrmRequest->update(['reviewed_by' => $request->user()->id]);
        if ($status === HrmApprovalWorkflow::STATUS_APPROVED) {
            HrmNotifier::notify(
                $hrmRequest->employee_id,
                'request_approved',
                'تأیید درخواست',
                'درخواست '.$hrmRequest->type.' #'.$hrmRequest->id.' تأیید شد.'
            );
        }

        return response()->json([
            'data' => $this->formatRequest($hrmRequest->fresh('user')),
            'message' => $status === 'approved' ? 'Approved' : 'Advanced to '.$status,
        ]);
    }

    public function requestReject(Request $request, HrmRequest $hrmRequest): JsonResponse
    {
        $data = $request->validate(['notes' => 'nullable|string|max:2000']);
        $wf = app(HrmApprovalWorkflow::class);
        $wf->rejectModel($hrmRequest, $request->user(), $data['notes'] ?? null);
        $hrmRequest->update(['reviewed_by' => $request->user()->id]);
        HrmNotifier::notify(
            $hrmRequest->employee_id,
            'request_rejected',
            'رد درخواست',
            'درخواست '.$hrmRequest->type.' #'.$hrmRequest->id.' رد شد.'
        );

        return response()->json(['data' => $this->formatRequest($hrmRequest->fresh('user')), 'message' => __('messages.rejected')]);
    }

    public function requestsInbox(): JsonResponse
    {
        $wf = app(HrmApprovalWorkflow::class);
        $items = $wf->cartableFor(auth()->user());

        return response()->json(['data' => $items]);
    }

    public function myDocuments(): JsonResponse
    {
        $employee = $this->resolveEmployee();
        if (! $employee || ! Schema::hasTable('hrm_personnel_documents')) {
            return response()->json(['data' => ['documents' => []]]);
        }

        $docs = HrmPersonnelDocument::query()
            ->where('employee_id', $employee->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (HrmPersonnelDocument $d) => [
                'id' => $d->id,
                'title' => $d->title,
                'category' => $d->category,
                'document_type' => $d->document_type,
                'file_name' => $d->file_name,
                'created_at' => optional($d->created_at)?->toIso8601String(),
            ])
            ->values()
            ->all();

        return response()->json(['data' => ['documents' => $docs]]);
    }

    public function myDocumentsStore(Request $request): JsonResponse
    {
        $employee = $this->resolveEmployee();
        if (! $employee) {
            return response()->json(['message' => __('messages.no_employee_profile_linked_to_this_user')], 404);
        }

        $data = $request->validate([
            'title' => 'required|string|max:200',
            'category' => 'nullable|string|max:100',
            'document_type' => 'nullable|string|max:100',
            'file' => 'required|file|max:10240',
        ]);

        $file = $request->file('file');
        $path = $file->store('hrm/personnel/' . $employee->id, 'public');

        $doc = HrmPersonnelDocument::query()->create([
            'employee_id' => $employee->id,
            'title' => $data['title'],
            'category' => $data['category'] ?? 'general',
            'document_type' => $data['document_type'] ?? 'user_upload',
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'mime_type' => $file->getClientMimeType(),
        ]);

        return response()->json(['data' => $doc, 'message' => __('messages.saved')], 201);
    }

    public function myDocumentDownload(HrmPersonnelDocument $document)
    {
        $employee = $this->resolveEmployee();
        if (! $employee || $document->employee_id !== $employee->id) {
            abort(403);
        }

        if (! Storage::disk('public')->exists($document->file_path)) {
            abort(404, 'File not found');
        }

        return Storage::disk('public')->download($document->file_path, $document->file_name);
    }

    protected function formatRequest(HrmRequest $r): array
    {
        return [
            'id' => $r->id,
            'type' => $r->type,
            'status' => $r->status,
            'approval_step' => $r->approval_step ?? null,
            'current_role' => $r->current_role ?? null,
            'approval_log' => $r->approval_log ?? [],
            'user_id' => $r->user_id,
            'employee_id' => $r->employee_id,
            'user_name' => $r->user?->name ?? $r->user?->email ?? (string) $r->user_id,
            'payload' => $r->payload,
            'notes' => $r->notes,
            'created_at' => optional($r->created_at)?->toIso8601String(),
        ];
    }
}

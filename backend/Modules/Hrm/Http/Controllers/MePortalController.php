<?php

namespace Modules\Hrm\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\Hrm\Entities\HrmAttendanceRecord;
use Modules\Hrm\Entities\HrmDependent;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmEmployeeProfile;
use Modules\Hrm\Entities\HrmEmploymentDecree;
use Modules\Hrm\Entities\HrmLeaveBalance;
use Modules\Hrm\Entities\HrmLoan;
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

        return HrmEmployee::query()->where('user_id', $userId)->first();
    }

    protected function noEmployeePayload(): array
    {
        return [
            'identity' => null,
            'decree' => null,
            'leave_balances' => [],
            'latest_payslip' => null,
            'open_requests' => 0,
        ];
    }

    public function me(): JsonResponse
    {
        $employee = $this->resolveEmployee();
        if (! $employee) {
            return response()->json(['data' => $this->noEmployeePayload()]);
        }

        $employee->load(['profile', 'shiftTemplate']);
        $custom = $employee->profile?->custom_fields ?? [];

        $decree = null;
        if (Schema::hasTable('hrm_employment_decrees')) {
            $row = HrmEmploymentDecree::query()
                ->where('employee_id', $employee->id)
                ->whereIn('status', ['active', 'approved', 'issued'])
                ->orderByDesc('effective_from')
                ->orderByDesc('id')
                ->first()
                ?? HrmEmploymentDecree::query()
                    ->where('employee_id', $employee->id)
                    ->orderByDesc('effective_from')
                    ->orderByDesc('id')
                    ->first();
            if ($row) {
                $decree = $this->formatDecree($row);
            }
        }

        $leaveBalances = [];
        if (Schema::hasTable('hrm_leave_balances')) {
            $year = (int) now()->format('Y');
            $leaveBalances = HrmLeaveBalance::query()
                ->with('leaveType')
                ->where('employee_id', $employee->id)
                ->where('year', $year)
                ->get()
                ->map(fn (HrmLeaveBalance $b) => [
                    'leave_type_id' => $b->leave_type_id,
                    'type_name' => $b->leaveType?->name ?? '',
                    'year' => $b->year,
                    'allocated' => (float) $b->allocated,
                    'used' => (float) $b->used,
                    'balance' => (float) $b->allocated - (float) $b->used,
                ])
                ->values()
                ->all();
        }

        $latestPayslip = null;
        if (Schema::hasTable('hrm_payroll_items')) {
            $item = HrmPayrollItem::query()
                ->with('payrollRun')
                ->where('employee_id', $employee->id)
                ->orderByDesc('id')
                ->first();
            if ($item) {
                $run = $item->payrollRun;
                $latestPayslip = [
                    'id' => $item->id,
                    'run_title' => $run?->title ?? '',
                    'jalali_year' => $run?->year ?? 0,
                    'jalali_month' => $run?->month ?? 0,
                    'gross' => (float) $item->gross,
                    'net' => (float) $item->net,
                    'deposit_date' => null,
                ];
            }
        }

        $openRequests = 0;
        if (Schema::hasTable('hrm_requests')) {
            $openRequests = HrmRequest::query()
                ->where('employee_id', $employee->id)
                ->whereIn('status', ['pending_manager', 'pending_hr', 'draft'])
                ->count();
        }

        $loanBalance = 0.0;
        $advanceBalance = 0.0;
        if (Schema::hasTable('hrm_loans')) {
            $loanBalance = (float) HrmLoan::query()->where('employee_id', $employee->id)->where('type', 'loan')->where('status', 'active')->sum('remaining_balance');
            $advanceBalance = (float) HrmLoan::query()->where('employee_id', $employee->id)->where('type', 'advance')->where('status', 'active')->sum('remaining_balance');
        }

        $missing = [];
        if (Schema::hasTable('hrm_personnel_documents')) {
            $cats = HrmPersonnelDocument::query()->where('employee_id', $employee->id)->pluck('category')->all();
            if (! in_array('national_card', $cats, true)) {
                $missing[] = 'national_card';
            }
            if (! in_array('contract', $cats, true)) {
                $missing[] = 'contract';
            }
            if ($missing !== []) {
                HrmNotifier::notify(
                    $employee->id,
                    'missing_docs',
                    'نقص مدارک پرسنلی',
                    'مدارک ناقص: '.implode(', ', $missing)
                );
            }
        }

        return response()->json([
            'data' => [
                'identity' => [
                    'user_id' => $employee->user_id,
                    'employee_id' => $employee->id,
                    'display_name' => trim($employee->first_name.' '.$employee->last_name),
                    'first_name' => $employee->first_name,
                    'last_name' => $employee->last_name,
                    'email' => $employee->email,
                    'personnel_code' => $employee->employee_code,
                    'national_id' => $employee->profile?->national_id,
                    'insurance_number' => $custom['insurance_number'] ?? null,
                    'job_title' => $employee->position,
                    'department' => $employee->department,
                    'direct_manager' => isset($custom['direct_manager'])
                        ? ['name' => (string) $custom['direct_manager']]
                        : null,
                    'workshop' => isset($custom['workshop']) && is_array($custom['workshop'])
                        ? $custom['workshop']
                        : null,
                    'hire_date' => optional($employee->hire_date)?->toDateString(),
                    'contract_type' => $employee->contract_type ?? ($custom['contract_type'] ?? ($decree['contract_type'] ?? null)),
                    'contract_end_date' => optional($employee->contract_end_date)?->toDateString(),
                    'contract_status' => $employee->contract_status,
                ],
                'decree' => $decree,
                'leave_balances' => $leaveBalances,
                'latest_payslip' => $latestPayslip,
                'open_requests' => $openRequests,
                'compensation' => [
                    'loan_balance' => $loanBalance,
                    'advance_balance' => $advanceBalance,
                ],
                'missing_documents' => $missing,
            ],
        ]);
    }

    public function notices(): JsonResponse
    {
        if (! Schema::hasTable('hrm_notices')) {
            return response()->json(['data' => ['notices' => []]]);
        }

        $today = now()->toDateString();
        $employee = $this->resolveEmployee();
        $notices = HrmNotice::query()
            ->where('is_active', true)
            ->when(
                Schema::hasColumn('hrm_notices', 'employee_id'),
                fn ($q) => $q->where(function ($inner) use ($employee) {
                    $inner->whereNull('employee_id');
                    if ($employee) {
                        $inner->orWhere('employee_id', $employee->id);
                    }
                })
            )
            ->where(fn ($q) => $q->whereNull('date_from')->orWhere('date_from', '<=', $today))
            ->where(fn ($q) => $q->whereNull('date_to')->orWhere('date_to', '>=', $today))
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (HrmNotice $n) => [
                'id' => $n->id,
                'title' => $n->title,
                'body' => $n->body,
                'date_from' => optional($n->date_from)?->toDateString(),
                'date_to' => optional($n->date_to)?->toDateString(),
            ])
            ->values()
            ->all();

        return response()->json(['data' => ['notices' => $notices]]);
    }

    public function shift(): JsonResponse
    {
        $employee = $this->resolveEmployee();
        if (! $employee) {
            return response()->json(['data' => ['shift' => null]]);
        }

        $shift = $employee->shiftTemplate;
        if (! $shift) {
            return response()->json(['data' => ['shift' => null]]);
        }

        return response()->json([
            'data' => [
                'shift' => [
                    'id' => $shift->id,
                    'name' => $shift->name,
                    'start_time' => $shift->start_time,
                    'end_time' => $shift->end_time,
                    'grace_minutes' => $shift->grace_minutes,
                ],
            ],
        ]);
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
            return response()->json(['message' => 'No employee profile linked to this user'], 404);
        }

        $data = $request->validate([
            'action' => 'nullable|in:in,out',
            'notes' => 'nullable|string|max:500',
        ]);

        $today = now()->toDateString();
        $record = HrmAttendanceRecord::query()->firstOrCreate(
            ['employee_id' => $employee->id, 'date' => $today],
            ['status' => 'present']
        );

        $action = $data['action'] ?? null;
        if (! $action) {
            $action = $record->check_in && ! $record->check_out ? 'out' : 'in';
        }

        if ($action === 'in') {
            $record->update([
                'check_in' => now()->format('H:i:s'),
                'notes' => $data['notes'] ?? $record->notes,
            ]);
            $message = 'Checked in';
        } else {
            if (! $record->check_in) {
                $record->update(['check_in' => now()->format('H:i:s')]);
            }
            $record->update([
                'check_out' => now()->format('H:i:s'),
                'notes' => $data['notes'] ?? $record->notes,
            ]);
            $message = 'Checked out';
        }

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
                'effective_from' => optional($d->effective_from)?->toDateString(),
                'effective_to' => optional($d->effective_to)?->toDateString(),
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
                'is_insured' => (bool) ($d->is_insured ?? true),
                'coverage_start' => optional($d->coverage_start)?->toDateString(),
            ])
            ->values()
            ->all();

        return response()->json(['data' => ['dependents' => $dependents]]);
    }

    public function dependentsStore(Request $request): JsonResponse
    {
        $employee = $this->resolveEmployee();
        if (! $employee) {
            return response()->json(['message' => 'No employee profile linked to this user'], 404);
        }

        $data = $request->validate([
            'full_name' => 'required|string|max:150',
            'relation' => 'nullable|string|max:50',
            'national_id' => 'nullable|string|max:20',
            'birth_date' => 'nullable|date',
            'is_insured' => 'nullable|boolean',
            'coverage_start' => 'nullable|date',
        ]);
        $data['employee_id'] = $employee->id;
        $dependent = HrmDependent::create($data);

        return response()->json(['data' => $dependent, 'message' => 'Dependent saved'], 201);
    }

    public function orgChart(): JsonResponse
    {
        if (! Schema::hasTable('hrm_org_positions')) {
            return response()->json(['data' => ['departments' => [], 'positions' => []]]);
        }

        $positions = HrmOrgPosition::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $departments = [];
        $children = [];
        foreach ($positions as $p) {
            $row = [
                'id' => $p->id,
                'name' => $p->title,
                'parent' => $p->parent_id ?? 0,
                'department' => $p->department,
            ];
            if (! $p->parent_id) {
                $departments[] = $row;
            } else {
                $children[] = $row;
            }
        }

        return response()->json([
            'data' => [
                'departments' => $departments,
                'positions' => $children,
            ],
        ]);
    }

    public function profileShow(): JsonResponse
    {
        $employee = $this->resolveEmployee();
        if (! $employee) {
            return response()->json(['data' => ['profile' => null]]);
        }

        $profile = HrmEmployeeProfile::query()->firstOrCreate(['employee_id' => $employee->id]);
        $custom = $profile->custom_fields ?? [];

        return response()->json([
            'data' => [
                'profile' => [
                    'id' => $employee->id,
                    'first_name' => $employee->first_name,
                    'last_name' => $employee->last_name,
                    'email' => $employee->email,
                    'mobile' => $employee->mobile,
                    'address' => $profile->address,
                    'national_id' => $profile->national_id,
                    'iban' => $custom['iban'] ?? null,
                    'sections' => [
                        'contact_info' => [
                            'fields' => [
                                'mobile_phone' => ['value' => $employee->mobile],
                                'address' => ['value' => $profile->address],
                            ],
                        ],
                        'financial_info' => [
                            'fields' => [
                                'iban' => ['value' => $custom['iban'] ?? ''],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    public function profileUpdate(Request $request): JsonResponse
    {
        $employee = $this->resolveEmployee();
        if (! $employee) {
            return response()->json(['message' => 'No employee profile linked to this user'], 404);
        }

        $data = $request->validate([
            'mobile' => 'nullable|string|max:20',
            'mobile_phone' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'iban' => 'nullable|string|max:34',
            'email' => 'nullable|email|max:150',
        ]);

        $mobile = $data['mobile'] ?? $data['mobile_phone'] ?? null;
        if ($mobile !== null) {
            $employee->mobile = $mobile;
        }
        if (array_key_exists('email', $data) && $data['email'] !== null) {
            $employee->email = $data['email'];
        }
        $employee->save();

        $profile = HrmEmployeeProfile::query()->firstOrCreate(['employee_id' => $employee->id]);
        if (array_key_exists('address', $data)) {
            $profile->address = $data['address'];
        }
        $custom = $profile->custom_fields ?? [];
        if (array_key_exists('iban', $data)) {
            $custom['iban'] = $data['iban'];
            $profile->custom_fields = $custom;
        }
        $profile->save();

        return response()->json(['data' => ['profile' => $profile->fresh()], 'message' => 'Profile updated']);
    }

    public function requestsInbox(Request $request): JsonResponse
    {
        $wf = app(HrmApprovalWorkflow::class);
        $cartable = $wf->cartableFor($request->user());
        $requests = collect($cartable['requests'])->map(fn (HrmRequest $r) => [
            'id' => $r->id,
            'type' => $r->type,
            'status' => $r->status,
            'approval_step' => $r->approval_step ?? null,
            'current_role' => $r->current_role ?? null,
            'user_id' => $r->user_id,
            'user_name' => $r->user?->name ?? $r->user?->email ?? (string) $r->user_id,
            'payload' => $r->payload,
            'notes' => $r->notes,
            'created_at' => optional($r->created_at)?->toIso8601String(),
        ])->values()->all();
        $leaves = collect($cartable['leaves'])->map(fn ($l) => [
            'id' => $l->id,
            'type' => 'leave',
            'leave_type' => $l->type,
            'status' => $l->status,
            'approval_step' => $l->approval_step ?? null,
            'current_role' => $l->current_role ?? null,
            'employee_id' => $l->employee_id,
            'start_date' => optional($l->start_date)?->toDateString(),
            'end_date' => optional($l->end_date)?->toDateString(),
            'created_at' => optional($l->created_at)?->toIso8601String(),
        ])->values()->all();

        return response()->json(['data' => [
            'requests' => $requests,
            'leaves' => $leaves,
            'pending_for_roles' => $wf->pendingStatuses(),
        ]]);
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

                return [
                    'id' => $item->id,
                    'run_title' => $run?->title ?? '',
                    'jalali_year' => $run?->year ?? 0,
                    'jalali_month' => $run?->month ?? 0,
                    'gross' => (float) $item->gross,
                    'net' => (float) $item->net,
                    'deductions' => (float) $item->deductions,
                    'days_worked' => null,
                    'loan_deduction' => ($item->breakdown ?? [])['loan_deduction'] ?? null,
                    'advance_deduction' => ($item->breakdown ?? [])['advance_deduction'] ?? null,
                    'employee_insurance' => ($item->breakdown ?? [])['employee_insurance'] ?? null,
                    'employer_insurance' => ($item->breakdown ?? [])['employer_insurance'] ?? null,
                    'tax' => ($item->breakdown ?? [])['tax'] ?? null,
                    'overtime' => ($item->breakdown ?? [])['overtime'] ?? null,
                    'iban' => null,
                    'deposit_date' => null,
                    'breakdown' => $item->breakdown,
                ];
            })
            ->values()
            ->all();

        return response()->json(['data' => ['payslips' => $payslips]]);
    }

    public function payrollDecreesIndex(): JsonResponse
    {
        if (! Schema::hasTable('hrm_employment_decrees')) {
            return response()->json(['data' => ['decrees' => []]]);
        }

        $decrees = HrmEmploymentDecree::query()
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (HrmEmploymentDecree $d) => $this->formatDecree($d))
            ->values()
            ->all();

        return response()->json(['data' => ['decrees' => $decrees]]);
    }

    public function payrollDecreesStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => 'nullable|exists:users,id',
            'employee_id' => 'nullable|exists:hrm_employees,id',
            'decree_type' => 'nullable|string|max:40',
            'status' => 'nullable|string|max:30',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date',
            'job_title' => 'nullable|string|max:150',
            'department' => 'nullable|string|max:100',
            'job_code' => 'nullable|string|max:50',
            'contract_type' => 'nullable|string|max:50',
            'engagement_type' => 'nullable|in:full_time,part_time,contractor,freelance,project,remote',
            'pay_basis' => 'nullable|in:monthly,daily,hourly,project_fee',
            'daily_wage' => 'nullable|numeric|min:0',
            'hourly_rate' => 'nullable|numeric|min:0',
            'project_fee' => 'nullable|numeric|min:0',
            'base_salary' => 'nullable|numeric|min:0',
            'insurance_applicable' => 'nullable|boolean',
            'tax_applicable' => 'nullable|boolean',
            'decree_no' => 'nullable|string|max:50',
        ]);

        if (empty($data['employee_id']) && ! empty($data['user_id'])) {
            $emp = HrmEmployee::query()->where('user_id', $data['user_id'])->first();
            if ($emp) {
                $data['employee_id'] = $emp->id;
            }
        }

        $data['decree_type'] = $data['decree_type'] ?? 'hire';
        $data['status'] = $data['status'] ?? 'issued';
        $rules = app(HrmCompensationRules::class);
        $emp = ! empty($data['employee_id']) ? HrmEmployee::query()->find($data['employee_id']) : null;
        if ($emp) {
            $data['engagement_type'] = $data['engagement_type'] ?? $emp->engagement_type;
            $data['pay_basis'] = $data['pay_basis'] ?? $emp->pay_basis;
            $data['base_salary'] = $data['base_salary'] ?? $emp->base_salary;
            $data['project_fee'] = $data['project_fee'] ?? $emp->project_fee;
            $data['daily_wage'] = $data['daily_wage'] ?? $emp->daily_rate;
            $data['hourly_rate'] = $data['hourly_rate'] ?? $emp->hourly_rate;
            if (! array_key_exists('insurance_applicable', $data)) {
                $data['insurance_applicable'] = $emp->insurance_applicable;
            }
            if (! array_key_exists('tax_applicable', $data)) {
                $data['tax_applicable'] = $emp->tax_applicable;
            }
            $data['contract_type'] = $data['contract_type'] ?? $emp->contract_type;
            $data['job_title'] = $data['job_title'] ?? $emp->position;
            $data['department'] = $data['department'] ?? $emp->department;
        }
        $data = $rules->applyDefaults($data);
        if ($emp) {
            $probe = $emp->replicate();
            $probe->engagement_type = $data['engagement_type'];
            $probe->pay_basis = $data['pay_basis'];
            $probe->base_salary = $data['base_salary'] ?? 0;
            $probe->daily_rate = $data['daily_wage'] ?? 0;
            $probe->hourly_rate = $data['hourly_rate'] ?? 0;
            $probe->project_fee = $data['project_fee'] ?? 0;
            $probe->insurance_applicable = $data['insurance_applicable'];
            $probe->tax_applicable = $data['tax_applicable'];
            $rules->assertEmployee($probe);
            $emp->fill([
                'engagement_type' => $probe->engagement_type,
                'pay_basis' => $probe->pay_basis,
                'base_salary' => $probe->base_salary,
                'daily_rate' => $probe->daily_rate,
                'hourly_rate' => $probe->hourly_rate,
                'project_fee' => $probe->project_fee,
                'insurance_applicable' => $probe->insurance_applicable,
                'tax_applicable' => $probe->tax_applicable,
                'contract_type' => $data['contract_type'] ?? $emp->contract_type,
            ])->save();
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('hrm_payroll_components')) {
            $data['benefits'] = HrmPayrollComponent::query()->where('is_active', true)->where('type', 'earning')->get()
                ->map(fn ($c) => ['code' => $c->code, 'name' => $c->name, 'amount' => (float) $c->default_amount])
                ->values()->all();
        }
        if (empty($data['decree_no'])) {
            $data['decree_no'] = 'D-'.now()->format('Ymd').'-'.str_pad((string) (HrmEmploymentDecree::query()->count() + 1), 4, '0', STR_PAD_LEFT);
        }

        $decree = HrmEmploymentDecree::create($data);

        return response()->json(['data' => $this->formatDecree($decree), 'message' => 'Decree saved'], 201);
    }

    protected function formatDecree(HrmEmploymentDecree $d): array
    {
        return [
            'id' => $d->id,
            'employee_id' => $d->employee_id,
            'user_id' => $d->user_id,
            'decree_no' => $d->decree_no,
            'decree_type' => $d->decree_type,
            'status' => $d->status,
            'effective_from' => optional($d->effective_from)?->toDateString(),
            'effective_to' => optional($d->effective_to)?->toDateString(),
            'job_title' => $d->job_title,
            'department' => $d->department,
            'contract_type' => $d->contract_type,
            'engagement_type' => $d->engagement_type,
            'pay_basis' => $d->pay_basis,
            'job_code' => $d->job_code,
            'base_salary' => (float) $d->base_salary,
            'daily_wage' => (float) $d->daily_wage,
            'project_fee' => (float) ($d->project_fee ?? 0),
            'hourly_rate' => (float) ($d->hourly_rate ?? 0),
            'insurance_applicable' => (bool) ($d->insurance_applicable ?? true),
            'tax_applicable' => (bool) ($d->tax_applicable ?? true),
            'benefits' => $d->benefits,
            'workshop_id' => $d->workshop_id,
            'serial_no' => $d->serial_no,
            'signer_name' => $d->signer_name,
            'signer_role' => $d->signer_role,
            'stamp_path' => $d->stamp_path,
        ];
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
            return response()->json(['message' => 'No employee profile linked to this user'], 404);
        }

        $data = $request->validate([
            'type' => 'required|in:overtime,mission,remote_work,remote,loan,advance,leave',
            'notes' => 'nullable|string|max:2000',
            'payload' => 'nullable|array',
            'payload.hours' => 'nullable|numeric|min:0',
            'payload.days' => 'nullable|numeric|min:0',
            'payload.amount' => 'nullable|numeric|min:0',
            'payload.date' => 'nullable|date',
            'payload.year' => 'nullable|integer',
            'payload.month' => 'nullable|integer|min:1|max:12',
            'payload.location' => 'nullable|string|max:200',
        ]);

        $wf = app(HrmApprovalWorkflow::class);
        $boot = Schema::hasColumn('hrm_requests', 'approval_step')
            ? $wf->bootstrapFields()
            : ['status' => 'pending_manager'];
        $row = HrmRequest::query()->create(array_merge([
            'employee_id' => $employee->id,
            'user_id' => auth()->id(),
            'type' => $data['type'],
            'payload' => $data['payload'] ?? [],
            'notes' => $data['notes'] ?? null,
        ], $boot));

        return response()->json(['data' => $this->formatRequest($row), 'message' => 'Request submitted'], 201);
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

        return response()->json(['data' => $this->formatRequest($hrmRequest->fresh('user')), 'message' => 'Rejected']);
    }

    public function myDocuments(): JsonResponse
    {
        $employee = $this->resolveEmployee();
        if (! $employee || ! Schema::hasTable('hrm_personnel_documents')) {
            return response()->json(['data' => ['documents' => []]]);
        }

        return response()->json(['data' => ['documents' => $this->documentRows($employee->id)]]);
    }

    public function myDocumentsStore(Request $request): JsonResponse
    {
        $employee = $this->resolveEmployee();
        if (! $employee) {
            return response()->json(['message' => 'No employee profile linked to this user'], 404);
        }

        return $this->storeDocument($request, $employee);
    }

    public function myDocumentDownload(HrmPersonnelDocument $document)
    {
        $employee = $this->resolveEmployee();
        abort_unless($employee && $document->employee_id === $employee->id, 404);

        return $this->downloadDocument($document);
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

    /**
     * @return list<array<string, mixed>>
     */
    protected function documentRows(int $employeeId): array
    {
        return HrmPersonnelDocument::query()
            ->where('employee_id', $employeeId)
            ->orderByDesc('id')
            ->get()
            ->map(fn (HrmPersonnelDocument $d) => [
                'id' => $d->id,
                'title' => $d->title,
                'category' => $d->category,
                'original_name' => $d->original_name,
                'mime' => $d->mime,
                'size' => $d->size,
                'expires_at' => optional($d->expires_at)?->toDateString(),
                'created_at' => optional($d->created_at)?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    protected function storeDocument(Request $request, HrmEmployee $employee): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:200',
            'category' => 'nullable|string|max:40',
            'file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,webp',
            'expires_at' => 'nullable|date',
        ]);
        $file = $request->file('file');
        $path = $file->store('hrm/documents/'.$employee->id, 'local');
        $doc = HrmPersonnelDocument::query()->create([
            'employee_id' => $employee->id,
            'title' => $data['title'],
            'category' => $data['category'] ?? 'other',
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize() ?: 0,
            'uploaded_by' => $request->user()?->id,
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        return response()->json(['data' => $doc, 'message' => 'Document stored'], 201);
    }

    protected function downloadDocument(HrmPersonnelDocument $document)
    {
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, $document->original_name);
    }
}

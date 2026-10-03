<?php

namespace Modules\Hrm\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmEmployeeProfile;
use Modules\Hrm\Entities\HrmEmployeeSalary;
use Modules\Hrm\Entities\HrmEmploymentDecree;
use Modules\Hrm\Entities\HrmOrgPosition;
use Modules\Hrm\Support\IranianNationalId;

class HrmEmployeeOnboarding
{
    public const STATUSES = ['active', 'inactive', 'terminated', 'resigned'];

    public const CONTRACT_TYPES = ['permanent', 'fixed_term', 'temporary', 'hourly', 'project', 'internship', 'consulting'];

    public const CONTRACT_STATUSES = ['draft', 'active', 'suspended', 'expired', 'terminated', 'renewed'];

    public function create(Request $request): HrmEmployee
    {
        $data = $this->validatePayload($request, null);

        return DB::transaction(fn () => $this->persist(null, $data, (int) $request->user()->id));
    }

    public function update(Request $request, HrmEmployee $employee): HrmEmployee
    {
        $data = $this->validatePayload($request, $employee);

        return DB::transaction(fn () => $this->persist($employee, $data, (int) $request->user()->id));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, ?HrmEmployee $employee): array
    {
        $unique = 'unique:hrm_employees,employee_code';
        if ($employee) {
            $unique .= ','.$employee->id;
        }

        $data = $request->validate([
            'employee_code' => ['required', 'string', 'max:20', $unique],
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'nullable|email|max:150',
            'mobile' => 'nullable|string|max:20',
            'status' => 'required|in:'.implode(',', self::STATUSES),
            'org_position_id' => 'required|exists:hrm_org_positions,id',
            'hire_date' => 'nullable|date',
            'contract_start_date' => 'nullable|date',
            'contract_end_date' => 'nullable|date',
            'contract_type' => 'required|in:'.implode(',', self::CONTRACT_TYPES),
            'contract_status' => 'required|in:'.implode(',', self::CONTRACT_STATUSES),
            'engagement_type' => 'required|in:'.implode(',', HrmCompensationRules::ENGAGEMENTS),
            'pay_basis' => 'required|in:'.implode(',', HrmCompensationRules::PAY_BASES),
            'project_fee' => 'nullable|numeric|min:0',
            'daily_rate' => 'nullable|numeric|min:0',
            'hourly_rate' => 'nullable|numeric|min:0',
            'base_salary' => 'nullable|numeric|min:0',
            'insurance_applicable' => 'nullable|boolean',
            'tax_applicable' => 'nullable|boolean',
            'notes' => 'nullable|string',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:female,male,other',
            'address' => 'nullable|string',
            'salary_components' => 'nullable|array',
            'salary_components.*.code' => 'nullable|string|max:40',
            'salary_components.*.name' => 'nullable|string|max:150',
            'salary_components.*.type' => 'nullable|in:earning,deduction',
            'salary_components.*.calculation' => 'nullable|string|max:40',
            'salary_components.*.amount' => 'nullable|numeric|min:0',
            'salary_components.*.enabled' => 'nullable|boolean',
            'issue_decree' => 'nullable|boolean',
        ]);

        $code = IranianNationalId::normalize((string) $data['employee_code']);
        if (! IranianNationalId::isValid($code)) {
            throw ValidationException::withMessages([
                'employee_code' => 'Personnel code must be a valid 10-digit Iranian national ID.',
            ]);
        }
        $data['employee_code'] = $code;

        $position = HrmOrgPosition::query()->findOrFail($data['org_position_id']);
        if ($position->is_active === false) {
            throw ValidationException::withMessages([
                'org_position_id' => 'Choose an active organization position.',
            ]);
        }
        $data['position'] = $position->title;
        $data['department'] = $position->department;
        $data['_position'] = $position;

        if (empty($data['contract_start_date']) && ! empty($data['hire_date'])) {
            $data['contract_start_date'] = $data['hire_date'];
        }
        if (! empty($data['contract_end_date']) && ! empty($data['contract_start_date']) && $data['contract_end_date'] < $data['contract_start_date']) {
            throw ValidationException::withMessages([
                'contract_end_date' => 'Contract end cannot be before contract start.',
            ]);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function persist(?HrmEmployee $employee, array $data, int $userId): HrmEmployee
    {
        $rules = app(HrmCompensationRules::class);
        $attrs = [
            'employee_code' => $data['employee_code'],
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'] ?? null,
            'mobile' => $data['mobile'] ?? null,
            'department' => $data['department'] ?? null,
            'position' => $data['position'] ?? null,
            'hire_date' => $data['hire_date'] ?? null,
            'contract_type' => $data['contract_type'],
            'contract_end_date' => $data['contract_end_date'] ?? null,
            'contract_status' => $data['contract_status'],
            'engagement_type' => $data['engagement_type'],
            'pay_basis' => $data['pay_basis'],
            'project_fee' => $data['project_fee'] ?? null,
            'daily_rate' => $data['daily_rate'] ?? null,
            'hourly_rate' => $data['hourly_rate'] ?? null,
            'insurance_applicable' => $data['insurance_applicable'] ?? null,
            'tax_applicable' => $data['tax_applicable'] ?? null,
            'status' => $data['status'],
            'base_salary' => $data['base_salary'] ?? null,
            'notes' => $data['notes'] ?? ($employee->notes ?? null),
        ];
        if (Schema::hasColumn('hrm_employees', 'contract_start_date')) {
            $attrs['contract_start_date'] = $data['contract_start_date'] ?? null;
        }
        $attrs = $rules->applyDefaults($attrs);
        $rules->assertEmployee(new HrmEmployee(array_merge($employee?->toArray() ?? [], $attrs)));

        if ($employee) {
            $employee->update($attrs);
        } else {
            $attrs['created_by'] = $userId;
            $employee = HrmEmployee::query()->create($attrs);
        }

        if (Schema::hasTable('hrm_employee_profiles')) {
            HrmEmployeeProfile::query()->updateOrCreate(
                ['employee_id' => $employee->id],
                [
                    'national_id' => $data['employee_code'],
                    'birth_date' => $data['birth_date'] ?? null,
                    'gender' => $data['gender'] ?? null,
                    'address' => $data['address'] ?? null,
                ]
            );
        }

        $components = array_values(array_filter(
            $data['salary_components'] ?? [],
            fn ($row) => is_array($row) && ! empty($row['code'])
        ));
        $primary = match ($attrs['pay_basis']) {
            'daily' => (float) ($attrs['daily_rate'] ?? 0),
            'hourly' => (float) ($attrs['hourly_rate'] ?? 0),
            'project_fee' => (float) ($attrs['project_fee'] ?? 0),
            default => (float) ($attrs['base_salary'] ?? 0),
        };
        if (Schema::hasTable('hrm_employee_salaries')) {
            HrmEmployeeSalary::query()->create([
                'employee_id' => $employee->id,
                'base_salary' => $primary,
                'components' => $components,
                'effective_from' => $data['contract_start_date'] ?? $data['hire_date'] ?? now()->toDateString(),
                'effective_to' => $data['contract_end_date'] ?? null,
            ]);
        }

        $position = $data['_position'] ?? null;
        if ($position instanceof HrmOrgPosition && Schema::hasColumn('hrm_org_positions', 'incumbent_employee_id')) {
            $position->update(['incumbent_employee_id' => $employee->id]);
        }

        if (! empty($data['issue_decree']) && Schema::hasTable('hrm_employment_decrees')) {
            $this->issueDecree($employee, $attrs, $components, $employee->wasRecentlyCreated ? 'hire' : 'amendment');
        }

        return $employee->fresh(['profile']);
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @param  list<array<string, mixed>>  $components
     */
    private function issueDecree(HrmEmployee $employee, array $attrs, array $components, string $type): void
    {
        $benefits = [];
        foreach ($components as $row) {
            if (array_key_exists('enabled', $row) && $row['enabled'] === false) {
                continue;
            }
            $benefits[] = [
                'code' => $row['code'] ?? null,
                'name' => $row['name'] ?? null,
                'type' => $row['type'] ?? 'earning',
                'amount' => (float) ($row['amount'] ?? 0),
            ];
        }
        $from = $attrs['contract_start_date'] ?? $attrs['hire_date'] ?? now()->toDateString();
        HrmEmploymentDecree::query()->create([
            'employee_id' => $employee->id,
            'decree_no' => 'D-'.now()->format('Ymd').'-'.str_pad((string) (HrmEmploymentDecree::query()->count() + 1), 4, '0', STR_PAD_LEFT),
            'decree_type' => $type,
            'status' => 'issued',
            'effective_from' => $from,
            'effective_to' => $attrs['contract_end_date'] ?? null,
            'job_title' => $attrs['position'] ?? null,
            'department' => $attrs['department'] ?? null,
            'contract_type' => $attrs['contract_type'] ?? null,
            'engagement_type' => $attrs['engagement_type'] ?? null,
            'pay_basis' => $attrs['pay_basis'] ?? null,
            'base_salary' => $attrs['base_salary'] ?? 0,
            'daily_wage' => $attrs['daily_rate'] ?? 0,
            'hourly_rate' => $attrs['hourly_rate'] ?? 0,
            'project_fee' => $attrs['project_fee'] ?? 0,
            'insurance_applicable' => (bool) ($attrs['insurance_applicable'] ?? true),
            'tax_applicable' => (bool) ($attrs['tax_applicable'] ?? true),
            'benefits' => $benefits,
        ]);
    }
}

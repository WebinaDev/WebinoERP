<?php

namespace Modules\Hrm\Services;

use Illuminate\Support\Facades\Schema;
use Modules\Hrm\Entities\HrmApprovalFlow;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmLeaveRequest;
use Modules\Hrm\Entities\HrmOrgPosition;
use Modules\Hrm\Entities\HrmRequest;

/**
 * Multi-step cartable.
 *
 * Default chain comes from payroll settings (approval_chain: manager → hr → finance).
 * An active row in hrm_approval_flows for a request type (leave, overtime, mission, remote,
 * loan, advance, timesheet) replaces it. Each step is one of:
 *  - {type: role, role: hr_manager}        any user with that Spatie role
 *  - {type: direct_manager}                incumbent of the parent org-chart position (falls back to hr_manager)
 *  - {type: user, user_ids: [3, 7]}        one of the listed users
 * system_manager can always act.
 */
class HrmApprovalWorkflow
{
    public const STATUS_PENDING_MANAGER = 'pending_manager';

    public const STATUS_PENDING_HR = 'pending_hr';

    public const STATUS_PENDING_FINANCE = 'pending_finance';

    public const STATUS_PENDING_APPROVAL = 'pending_approval';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const REQUEST_TYPES = ['leave', 'overtime', 'mission', 'remote', 'loan', 'advance', 'timesheet'];

    public const STEP_TYPES = ['role', 'direct_manager', 'user'];

    /** @var array<string, list<array<string, mixed>>|null> */
    private array $stepCache = [];

    public static function normalizeType(?string $type): ?string
    {
        if ($type === null || $type === '') {
            return null;
        }

        return $type === 'remote_work' ? 'remote' : $type;
    }

    /**
     * @param  array<string, mixed>  $step
     * @return array{type: string, role: ?string, user_ids: list<int>}
     */
    public static function normalizeStep(array $step): array
    {
        $ids = array_values(array_unique(array_map('intval', is_array($step['user_ids'] ?? null) ? $step['user_ids'] : [])));
        $role = trim((string) ($step['role'] ?? ''));
        $type = (string) ($step['type'] ?? '');
        if (! in_array($type, self::STEP_TYPES, true)) {
            if ($role === 'direct_manager') {
                $type = 'direct_manager';
            } elseif ($ids !== [] && ($role === '' || $role === 'user')) {
                $type = 'user';
            } else {
                $type = 'role';
            }
        }

        return [
            'type' => $type,
            'role' => $type === 'role' ? ($role !== '' ? $role : 'hr_manager') : null,
            'user_ids' => $type === 'user' ? $ids : [],
        ];
    }

    /**
     * Role keys of the chain (stored in current_role).
     *
     * @return list<string>
     */
    public function chain(?string $requestType = null): array
    {
        $custom = $this->customSteps($requestType);
        if ($custom !== null) {
            return array_map(fn (array $s) => match ($s['type']) {
                'direct_manager' => 'direct_manager',
                'user' => 'user',
                default => (string) $s['role'],
            }, $custom);
        }

        return $this->settingsChain();
    }

    /**
     * @return list<string>
     */
    private function settingsChain(): array
    {
        $settings = app(IranianPayrollCalculator::class)->settings();
        $chain = $settings['approval_chain'] ?? ['manager', 'hr', 'finance'];

        return is_array($chain) && $chain !== [] ? array_values($chain) : ['manager', 'hr', 'finance'];
    }

    public function hasCustomFlow(?string $requestType): bool
    {
        return $this->customSteps($requestType) !== null;
    }

    /**
     * @return list<string>
     */
    public function activeRequestTypes(): array
    {
        if (! Schema::hasTable('hrm_approval_flows')) {
            return [];
        }

        return HrmApprovalFlow::query()->where('is_active', true)->pluck('request_type')->all();
    }

    /**
     * @return array<string, list<string>>
     */
    public function roleMap(): array
    {
        $settings = app(IranianPayrollCalculator::class)->settings();
        $map = $settings['approval_role_map'] ?? [
            'manager' => ['project_manager', 'system_manager'],
            'hr' => ['hr_manager', 'system_manager'],
            'finance' => ['finance_manager', 'system_manager'],
        ];

        return is_array($map) ? $map : [];
    }

    public function initialStatus(?string $requestType = null): string
    {
        return $this->statusForStep(1, $requestType);
    }

    public function statusForStep(int $step, ?string $requestType = null): string
    {
        $chain = $this->chain($requestType);
        $idx = max(1, $step) - 1;
        if ($idx >= count($chain)) {
            return self::STATUS_APPROVED;
        }

        return $this->statusToken((string) $chain[$idx]);
    }

    public function currentRoleForStep(int $step, ?string $requestType = null): ?string
    {
        $chain = $this->chain($requestType);

        return $chain[max(1, $step) - 1] ?? null;
    }

    public function userCanAct(?object $user, string $roleKey, ?string $requestType = null, ?int $step = null, ?object $model = null): bool
    {
        if (! $user) {
            return false;
        }
        if ($this->hasRole($user, 'system_manager')) {
            return true;
        }

        $custom = $this->customSteps($requestType);
        if ($custom !== null && $step) {
            $def = $custom[max(1, $step) - 1] ?? null;
            if (! $def) {
                return false;
            }

            return match ($def['type']) {
                'user' => in_array((int) ($user->id ?? 0), $def['user_ids'], true),
                'direct_manager' => $this->canActAsDirectManager($user, $model),
                default => $this->hasRole($user, (string) $def['role']),
            };
        }

        if (! method_exists($user, 'hasRole')) {
            return true;
        }
        // Without a custom flow, HR managers may act on manager and HR steps (HR override).
        if (in_array($roleKey, ['manager', 'direct_manager', 'hr'], true) && $this->hasRole($user, 'hr_manager')) {
            return true;
        }
        $roles = $this->roleMap()[$roleKey] ?? [$roleKey, $roleKey.'_manager', 'hr_manager', 'system_manager'];
        foreach ($roles as $role) {
            if ($this->hasRole($user, (string) $role)) {
                return true;
            }
        }

        return false;
    }

    public function canActOn(object $model, object $user): bool
    {
        $type = $this->typeOf($model);
        $step = (int) ($model->approval_step ?? 0) ?: 1;
        $role = (string) ($model->current_role ?? '') ?: ($this->currentRoleForStep($step, $type) ?? 'hr');

        return $this->userCanAct($user, $role, $type, $step, $model);
    }

    /**
     * @return list<string>
     */
    public function pendingStatuses(?string $requestType = null): array
    {
        $out = [];
        foreach ($this->chain($requestType) as $i => $_) {
            $out[] = $this->statusForStep($i + 1, $requestType);
        }

        return array_values(array_unique($out));
    }

    public function typeOf(object $model): ?string
    {
        $table = method_exists($model, 'getTable') ? $model->getTable() : '';

        return match ($table) {
            'hrm_leave_requests' => 'leave',
            'hrm_timesheets' => 'timesheet',
            'hrm_loans' => 'loan',
            default => isset($model->type) && is_string($model->type) ? self::normalizeType($model->type) : null,
        };
    }

    /**
     * Advance one step or approve at end. Returns new status.
     *
     * @param  array<string, mixed>  $logEntry
     */
    public function approveModel(object $model, object $user, array $logEntry = []): string
    {
        $type = $this->typeOf($model);
        $step = (int) ($model->approval_step ?? 1) ?: 1;
        $chain = $this->chain($type);
        $role = $this->currentRoleForStep($step, $type) ?? 'hr';
        if (! $this->userCanAct($user, $role, $type, $step, $model)) {
            abort(403, 'Not allowed for approval step: '.$role);
        }

        $log = is_array($model->approval_log ?? null) ? $model->approval_log : [];
        $log[] = array_merge([
            'action' => 'approve',
            'step' => $step,
            'role' => $role,
            'user_id' => $user->id ?? null,
            'at' => now()->toIso8601String(),
        ], $logEntry);

        $done = $step >= count($chain);
        $next = $done ? $step : $step + 1;
        $status = $done ? self::STATUS_APPROVED : $this->statusForStep($next, $type);
        $payload = ['status' => $status, 'approval_log' => $log];
        if (Schema::hasColumn($model->getTable(), 'approval_step')) {
            $payload['approval_step'] = $next;
        }
        if (Schema::hasColumn($model->getTable(), 'current_role')) {
            $payload['current_role'] = $done ? null : $this->currentRoleForStep($next, $type);
        }
        $model->update($payload);

        return $status;
    }

    /**
     * @param  array<string, mixed>  $logEntry
     */
    public function rejectModel(object $model, object $user, ?string $notes = null, array $logEntry = []): string
    {
        $type = $this->typeOf($model);
        $step = (int) ($model->approval_step ?? 1) ?: 1;
        $role = $this->currentRoleForStep($step, $type) ?? 'hr';
        if (! $this->userCanAct($user, $role, $type, $step, $model)) {
            abort(403, 'Not allowed for approval step: '.$role);
        }
        $log = is_array($model->approval_log ?? null) ? $model->approval_log : [];
        $log[] = array_merge([
            'action' => 'reject',
            'step' => $step,
            'role' => $role,
            'user_id' => $user->id ?? null,
            'notes' => $notes,
            'at' => now()->toIso8601String(),
        ], $logEntry);

        $payload = ['status' => self::STATUS_REJECTED, 'approval_log' => $log];
        if (Schema::hasColumn($model->getTable(), 'current_role')) {
            $payload['current_role'] = null;
        }
        if ($notes !== null && Schema::hasColumn($model->getTable(), 'notes')) {
            $payload['notes'] = $notes;
        }
        $model->update($payload);

        return self::STATUS_REJECTED;
    }

    /**
     * Approval fields for a new request. Callers check the columns exist.
     *
     * @return array<string, mixed>
     */
    public function bootstrapFields(?string $requestType = null): array
    {
        $type = self::normalizeType($requestType);

        return [
            'status' => $this->initialStatus($type),
            'approval_step' => 1,
            'current_role' => $this->currentRoleForStep(1, $type),
            'approval_log' => [],
        ];
    }

    /**
     * Items the user can act on right now.
     *
     * @return array{requests: list<HrmRequest>, leaves: list<HrmLeaveRequest>}
     */
    public function cartableFor(object $user): array
    {
        $requests = [];
        $leaves = [];
        if (Schema::hasTable('hrm_requests')) {
            $requests = HrmRequest::query()->with('user')
                ->where('status', 'like', 'pending%')
                ->orderByDesc('id')->limit(300)->get()
                ->filter(fn ($r) => $this->canActOn($r, $user))
                ->take(100)->values()->all();
        }
        if (Schema::hasTable('hrm_leave_requests')) {
            $leaves = HrmLeaveRequest::query()->with('employee')
                ->where('status', 'like', 'pending%')
                ->orderByDesc('id')->limit(300)->get()
                ->filter(fn ($l) => $this->canActOn($l, $user))
                ->take(100)->values()->all();
        }

        return ['requests' => $requests, 'leaves' => $leaves];
    }

    /**
     * User id of the requester's direct manager from the org chart (first filled parent position).
     */
    public function directManagerUserId(?object $model): ?int
    {
        $employeeId = $model->employee_id ?? null;
        if (! $employeeId && isset($model->user_id) && Schema::hasTable('hrm_employees')) {
            $employeeId = HrmEmployee::query()->where('user_id', $model->user_id)->value('id');
        }
        if (! $employeeId || ! Schema::hasTable('hrm_org_positions')) {
            return null;
        }
        $position = HrmOrgPosition::query()->where('incumbent_employee_id', $employeeId)->first();
        $guard = 0;
        while ($position && $position->parent_id && $guard++ < 12) {
            $position = HrmOrgPosition::query()->with('incumbent')->find($position->parent_id);
            $userId = $position?->incumbent?->user_id;
            if ($userId) {
                return (int) $userId;
            }
        }

        return null;
    }

    private function canActAsDirectManager(object $user, ?object $model): bool
    {
        $managerId = $model ? $this->directManagerUserId($model) : null;
        if ($managerId) {
            return (int) ($user->id ?? 0) === $managerId;
        }

        // No manager in the org chart: HR handles the step.
        return $this->hasRole($user, 'hr_manager');
    }

    private function hasRole(object $user, string $role): bool
    {
        try {
            return method_exists($user, 'hasRole') && $user->hasRole($role);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return list<array{type: string, role: ?string, user_ids: list<int>}>|null
     */
    private function customSteps(?string $requestType): ?array
    {
        $requestType = self::normalizeType($requestType);
        if ($requestType === null) {
            return null;
        }
        if (array_key_exists($requestType, $this->stepCache)) {
            return $this->stepCache[$requestType];
        }
        if (! Schema::hasTable('hrm_approval_flows')) {
            return $this->stepCache[$requestType] = null;
        }
        $row = HrmApprovalFlow::query()->where('request_type', $requestType)->where('is_active', true)->first();
        $steps = $row?->steps;
        if (! is_array($steps) || $steps === []) {
            return $this->stepCache[$requestType] = null;
        }

        return $this->stepCache[$requestType] = array_values(array_map(
            fn ($s) => self::normalizeStep(is_array($s) ? $s : []),
            $steps
        ));
    }

    public function forgetCache(): void
    {
        $this->stepCache = [];
    }

    private function statusToken(string $role): string
    {
        return match ($role) {
            'manager', 'direct_manager' => self::STATUS_PENDING_MANAGER,
            'hr', 'hr_manager' => self::STATUS_PENDING_HR,
            'finance', 'finance_manager' => self::STATUS_PENDING_FINANCE,
            'user' => self::STATUS_PENDING_APPROVAL,
            default => 'pending_'.strtolower((string) preg_replace('/[^a-z0-9_]+/i', '_', $role)),
        };
    }
}

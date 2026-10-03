<?php

namespace Modules\Hrm\Services;

use Illuminate\Support\Facades\Schema;
use Modules\Hrm\Entities\HrmLeaveRequest;
use Modules\Hrm\Entities\HrmRequest;

/**
 * Multi-step cartable: manager → HR → finance (configurable via payroll settings approval_chain).
 */
class HrmApprovalWorkflow
{
    public const STATUS_PENDING_MANAGER = 'pending_manager';

    public const STATUS_PENDING_HR = 'pending_hr';

    public const STATUS_PENDING_FINANCE = 'pending_finance';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /**
     * @return list<string>
     */
    public function chain(): array
    {
        $settings = app(IranianPayrollCalculator::class)->settings();
        $chain = $settings['approval_chain'] ?? ['manager', 'hr', 'finance'];

        return is_array($chain) && $chain !== [] ? array_values($chain) : ['manager', 'hr', 'finance'];
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

    public function initialStatus(): string
    {
        return $this->statusForStep(1);
    }

    public function statusForStep(int $step): string
    {
        $chain = $this->chain();
        $idx = max(1, $step) - 1;
        if ($idx >= count($chain)) {
            return self::STATUS_APPROVED;
        }
        $role = $chain[$idx];

        return match ($role) {
            'manager' => self::STATUS_PENDING_MANAGER,
            'hr' => self::STATUS_PENDING_HR,
            'finance' => self::STATUS_PENDING_FINANCE,
            default => 'pending_'.$role,
        };
    }

    public function currentRoleForStep(int $step): ?string
    {
        $chain = $this->chain();
        $idx = max(1, $step) - 1;

        return $chain[$idx] ?? null;
    }

    public function userCanAct(?object $user, string $roleKey): bool
    {
        if (! $user) {
            return false;
        }
        if (method_exists($user, 'hasRole') && $user->hasRole('system_manager')) {
            return true;
        }
        $roles = $this->roleMap()[$roleKey] ?? [$roleKey.'_manager', 'system_manager'];
        if (! method_exists($user, 'hasAnyRole') && ! method_exists($user, 'hasRole')) {
            return true;
        }
        foreach ($roles as $role) {
            if (method_exists($user, 'hasRole') && $user->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function pendingStatuses(): array
    {
        $out = [];
        foreach ($this->chain() as $i => $_) {
            $out[] = $this->statusForStep($i + 1);
        }

        return array_values(array_unique($out));
    }

    /**
     * Advance one step or approve at end. Returns new status.
     *
     * @param  array<string, mixed>  $logEntry
     */
    public function approveModel(object $model, object $user, array $logEntry = []): string
    {
        $step = (int) ($model->approval_step ?? 1);
        $chain = $this->chain();
        $role = $this->currentRoleForStep($step) ?? 'hr';
        if (! $this->userCanAct($user, $role)) {
            abort(403, 'Not allowed for approval step: '.$role);
        }

        $log = $model->approval_log ?? [];
        if (! is_array($log)) {
            $log = [];
        }
        $log[] = array_merge([
            'action' => 'approve',
            'step' => $step,
            'role' => $role,
            'user_id' => $user->id ?? null,
            'at' => now()->toIso8601String(),
        ], $logEntry);

        if ($step >= count($chain)) {
            $payload = [
                'status' => self::STATUS_APPROVED,
                'approval_log' => $log,
            ];
            if (Schema::hasColumn($model->getTable(), 'approval_step')) {
                $payload['approval_step'] = $step;
            }
            if (Schema::hasColumn($model->getTable(), 'current_role')) {
                $payload['current_role'] = null;
            }
            $model->update($payload);

            return self::STATUS_APPROVED;
        }

        $next = $step + 1;
        $status = $this->statusForStep($next);
        $payload = [
            'status' => $status,
            'approval_log' => $log,
        ];
        if (Schema::hasColumn($model->getTable(), 'approval_step')) {
            $payload['approval_step'] = $next;
        }
        if (Schema::hasColumn($model->getTable(), 'current_role')) {
            $payload['current_role'] = $this->currentRoleForStep($next);
        }
        $model->update($payload);

        return $status;
    }

    /**
     * @param  array<string, mixed>  $logEntry
     */
    public function rejectModel(object $model, object $user, ?string $notes = null, array $logEntry = []): string
    {
        $step = (int) ($model->approval_step ?? 1);
        $role = $this->currentRoleForStep($step) ?? 'hr';
        if (! $this->userCanAct($user, $role)) {
            abort(403, 'Not allowed for approval step: '.$role);
        }
        $log = $model->approval_log ?? [];
        if (! is_array($log)) {
            $log = [];
        }
        $log[] = array_merge([
            'action' => 'reject',
            'step' => $step,
            'role' => $role,
            'user_id' => $user->id ?? null,
            'notes' => $notes,
            'at' => now()->toIso8601String(),
        ], $logEntry);

        $payload = [
            'status' => self::STATUS_REJECTED,
            'approval_log' => $log,
        ];
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
     * Seed approval fields on create.
     *
     * @return array<string, mixed>
     */
    public function bootstrapFields(): array
    {
        $fields = [
            'status' => $this->initialStatus(),
        ];
        if (Schema::hasColumn('hrm_requests', 'approval_step') || Schema::hasColumn('hrm_leave_requests', 'approval_step')) {
            $fields['approval_step'] = 1;
            $fields['current_role'] = $this->currentRoleForStep(1);
            $fields['approval_log'] = [];
        }

        return $fields;
    }

    /**
     * Cartable: pending items for the acting user's current-step roles.
     *
     * @return array{requests: list<HrmRequest>, leaves: list<HrmLeaveRequest>}
     */
    public function cartableFor(object $user): array
    {
        $statuses = [];
        foreach ($this->chain() as $i => $roleKey) {
            if ($this->userCanAct($user, $roleKey)) {
                $statuses[] = $this->statusForStep($i + 1);
            }
        }
        $statuses = array_values(array_unique($statuses));
        $requests = [];
        $leaves = [];
        if ($statuses && Schema::hasTable('hrm_requests')) {
            $requests = HrmRequest::query()->with('user')->whereIn('status', $statuses)->orderByDesc('id')->limit(100)->get()->all();
        }
        if ($statuses && Schema::hasTable('hrm_leave_requests')) {
            // leave may still use legacy 'pending'
            $leaveStatuses = array_values(array_unique([...$statuses, 'pending']));
            $leaves = HrmLeaveRequest::query()->with('employee')->whereIn('status', $leaveStatuses)->orderByDesc('id')->limit(100)->get()->all();
        }

        return ['requests' => $requests, 'leaves' => $leaves];
    }
}

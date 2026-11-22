<?php

namespace Modules\Hrm\Services;

use InvalidArgumentException;
use Modules\Hrm\Entities\HrmPayrollItem;
use Modules\Hrm\Entities\HrmPayrollRun;
use Modules\Hrm\Entities\HrmPayrollRunAuditLog;

class HrmPayrollWorkflowEngine
{
    public const VALID_TRANSITIONS = [
        'draft' => ['calculating'],
        'calculating' => ['review'],
        'review' => ['approved', 'calculating'],
        'approved' => ['paid', 'review'],
        'paid' => ['locked'],
        'locked' => ['review'], // Controlled unlock by authorized roles only
    ];

    /**
     * Transition payroll run through strict workflow stages with audit logging.
     *
     * @throws InvalidArgumentException
     */
    public function transitionTo(HrmPayrollRun $run, string $targetState, ?int $userId = null, ?string $actionNote = null): HrmPayrollRun
    {
        $currentState = $run->workflow_state ?? 'draft';

        if ($currentState === $targetState) {
            return $run;
        }

        $allowed = self::VALID_TRANSITIONS[$currentState] ?? [];
        if (! in_array($targetState, $allowed, true)) {
            throw new InvalidArgumentException("انتقال وضعیت از «{$currentState}» به «{$targetState}» در چرخه حقوق مجاز نیست.");
        }

        // Snapshot key metrics before state change
        $snapshot = [
            'gross' => (float) $run->items()->sum('gross'),
            'net' => (float) $run->items()->sum('net'),
            'items_count' => $run->items()->count(),
            'timestamp' => now()->toIso8601String(),
        ];

        $updates = ['workflow_state' => $targetState];

        if ($targetState === 'review') {
            $updates['reviewed_at'] = now();
            $updates['reviewed_by'] = $userId;
        } elseif ($targetState === 'approved') {
            $updates['status'] = 'approved';
            $updates['approved_at'] = now();
        } elseif ($targetState === 'paid') {
            $updates['status'] = 'paid';
            $updates['paid_at'] = now();
        } elseif ($targetState === 'locked') {
            $updates['locked_at'] = now();
            $updates['locked_by'] = $userId;
        }

        $run->update($updates);

        if (\Illuminate\Support\Facades\Schema::hasTable('hrm_payroll_run_audit_logs')) {
            HrmPayrollRunAuditLog::query()->create([
                'payroll_run_id' => $run->id,
                'from_state' => $currentState,
                'to_state' => $targetState,
                'performed_by' => $userId,
                'action_note' => $actionNote ?? "تغییر سیستمی وضعیت چرخه حقوق به {$targetState}",
                'state_snapshot' => $snapshot,
            ]);
        }

        return $run->fresh();
    }
}

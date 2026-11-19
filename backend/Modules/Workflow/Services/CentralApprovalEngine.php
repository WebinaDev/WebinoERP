<?php

namespace Modules\Workflow\Services;

use App\Models\User;
use App\Support\StaffNotifier;
use Illuminate\Support\Facades\DB;
use Modules\Workflow\Entities\WfAuditLog;
use Modules\Workflow\Entities\WfDefinition;
use Modules\Workflow\Entities\WfDelegation;
use Modules\Workflow\Entities\WfInstance;
use Modules\Workflow\Entities\WfStepApproval;

class CentralApprovalEngine
{
    public function initiate(string $moduleSlug, string $entityType, int $entityId, string $title, ?float $amount = null, ?User $initiator = null, array $context = []): ?WfInstance
    {
        $definition = $this->resolveDefinition($moduleSlug, $entityType, $amount, $context);
        if (! $definition) {
            return null;
        }

        return DB::transaction(function () use ($definition, $entityType, $entityId, $title, $amount, $initiator, $context) {
            $instance = WfInstance::query()->create([
                'definition_id' => $definition->id,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'title' => $title,
                'amount' => $amount,
                'status' => 'pending',
                'current_step' => 1,
                'context' => $context,
                'initiated_by' => $initiator?->id,
            ]);

            $this->createStepApprovals($instance, 1);

            WfAuditLog::query()->create([
                'instance_id' => $instance->id,
                'user_id' => $initiator?->id,
                'action' => 'initiate',
                'payload' => ['definition_id' => $definition->id, 'context' => $context],
            ]);

            $this->notifyPendingApprovers($instance);

            return $instance;
        });
    }

    public function resolveDefinition(string $moduleSlug, string $entityType, ?float $amount = null, array $context = []): ?WfDefinition
    {
        $candidates = WfDefinition::query()
            ->where('module_slug', $moduleSlug)
            ->where('entity_type', $entityType)
            ->where('is_active', true)
            ->get();

        foreach ($candidates as $def) {
            $cond = $def->conditions ?? [];
            if (isset($cond['min_amount']) && $amount !== null && $amount < (float) $cond['min_amount']) {
                continue;
            }
            if (isset($cond['max_amount']) && $amount !== null && $amount > (float) $cond['max_amount']) {
                continue;
            }

            return $def;
        }

        return $candidates->first();
    }

    public function createStepApprovals(WfInstance $instance, int $stepOrder): void
    {
        $steps = $instance->definition->steps ?? [];
        $currentStepConfig = null;
        foreach ($steps as $s) {
            if ((int) ($s['order'] ?? 1) === $stepOrder) {
                $currentStepConfig = $s;
                break;
            }
        }

        if (! $currentStepConfig) {
            return;
        }

        $slaHours = (int) ($currentStepConfig['sla_hours'] ?? 24);
        $slaDueAt = now()->addHours($slaHours);

        $type = $currentStepConfig['type'] ?? 'role';
        $target = $currentStepConfig['target'] ?? 'system_manager';

        if ($type === 'user') {
            $userIds = is_array($target) ? $target : [(int) $target];
            foreach ($userIds as $uid) {
                WfStepApproval::query()->create([
                    'instance_id' => $instance->id,
                    'step_order' => $stepOrder,
                    'target_role' => null,
                    'target_user_id' => $uid,
                    'status' => 'pending',
                    'sla_due_at' => $slaDueAt,
                ]);
            }
        } else {
            WfStepApproval::query()->create([
                'instance_id' => $instance->id,
                'step_order' => $stepOrder,
                'target_role' => (string) $target,
                'target_user_id' => null,
                'status' => 'pending',
                'sla_due_at' => $slaDueAt,
            ]);
        }
    }

    public function decide(WfStepApproval $approval, User $user, string $decision, ?string $comment = null): WfInstance
    {
        return DB::transaction(function () use ($approval, $user, $decision, $comment) {
            $approval->update([
                'status' => $decision === 'approve' ? 'approved' : 'rejected',
                'decided_by' => $user->id,
                'decided_at' => now(),
                'comment' => $comment,
            ]);

            $instance = $approval->instance;

            WfAuditLog::query()->create([
                'instance_id' => $instance->id,
                'user_id' => $user->id,
                'action' => $decision === 'approve' ? 'approve' : 'reject',
                'payload' => ['step_order' => $approval->step_order, 'comment' => $comment],
            ]);

            if ($decision === 'reject') {
                $instance->update([
                    'status' => 'rejected',
                    'completed_at' => now(),
                ]);

                return $instance;
            }

            // Check if there are other pending approvals in this step (parallel mode)
            $pendingInStep = WfStepApproval::query()
                ->where('instance_id', $instance->id)
                ->where('step_order', $instance->current_step)
                ->where('status', 'pending')
                ->exists();

            if (! $pendingInStep) {
                $steps = $instance->definition->steps ?? [];
                $totalSteps = count($steps);

                if ($instance->current_step >= $totalSteps) {
                    $instance->update([
                        'status' => 'approved',
                        'completed_at' => now(),
                    ]);
                } else {
                    $nextStep = $instance->current_step + 1;
                    $instance->update(['current_step' => $nextStep]);
                    $this->createStepApprovals($instance, $nextStep);
                    $this->notifyPendingApprovers($instance);
                }
            }

            return $instance->fresh();
        });
    }

    public function userCanApprove(User $user, WfStepApproval $approval): bool
    {
        if ($user->hasRole('system_manager')) {
            return true;
        }

        // Direct user target
        if ($approval->target_user_id && (int) $approval->target_user_id === (int) $user->id) {
            return true;
        }

        // Check active delegations
        $hasDelegation = WfDelegation::query()
            ->where('delegate_id', $user->id)
            ->where('is_active', true)
            ->whereDate('start_date', '<=', now()->toDateString())
            ->whereDate('end_date', '>=', now()->toDateString())
            ->where(function ($q) use ($approval) {
                $mod = $approval->instance?->definition?->module_slug;
                $q->whereNull('module_slug')->orWhere('module_slug', $mod);
            })
            ->exists();

        if ($hasDelegation) {
            return true;
        }

        // Target role
        if ($approval->target_role && $user->hasRole($approval->target_role)) {
            return true;
        }

        return false;
    }

    private function notifyPendingApprovers(WfInstance $instance): void
    {
        $pending = WfStepApproval::query()
            ->where('instance_id', $instance->id)
            ->where('step_order', $instance->current_step)
            ->where('status', 'pending')
            ->get();

        foreach ($pending as $item) {
            if ($item->target_user_id) {
                $u = User::query()->find($item->target_user_id);
                if ($u) {
                    StaffNotifier::notify((int) $u->id, 'workflow.approval', "تاییدیه گردش کار: {$instance->title}", "درخواست تایید جدید برای {$instance->title} ثبت شد.");
                }
            } elseif ($item->target_role) {
                $users = User::role($item->target_role)->get();
                foreach ($users as $u) {
                    StaffNotifier::notify((int) $u->id, 'workflow.approval', "تاییدیه گردش کار: {$instance->title}", "درخواست تایید جدید با نقش {$item->target_role} برای {$instance->title} ثبت شد.");
                }
            }
        }
    }
}

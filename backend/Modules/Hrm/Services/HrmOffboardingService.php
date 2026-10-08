<?php

namespace Modules\Hrm\Services;

use Illuminate\Support\Facades\DB;
use Modules\Hrm\Entities\HrmEmployee;
use Modules\Hrm\Entities\HrmOffboarding;
use Modules\Hrm\Entities\HrmOffboardingTask;
use Modules\Hrm\Entities\HrmOffboardingTemplate;
use Modules\Hrm\Support\HrmNotifier;

class HrmOffboardingService
{
    public const KINDS = ['checklist', 'asset_return', 'access_revoke', 'settlement', 'exit_interview', 'handover', 'document'];

    public const OWNERS = ['employee', 'hr', 'it', 'finance', 'manager'];

    public const REASONS = ['resignation', 'termination', 'contract_end', 'retirement', 'transfer', 'other'];

    public function start(HrmEmployee $employee, int $templateId, ?string $lastDay = null, ?string $reason = null): HrmOffboarding
    {
        $template = HrmOffboardingTemplate::query()->with('items')->findOrFail($templateId);

        $row = DB::transaction(function () use ($employee, $template, $lastDay, $reason) {
            $row = HrmOffboarding::query()->create([
                'employee_id' => $employee->id,
                'template_id' => $template->id,
                'status' => 'in_progress',
                'reason' => $reason,
                'last_day' => $lastDay,
                'started_at' => now(),
            ]);
            foreach ($template->items as $item) {
                HrmOffboardingTask::query()->create([
                    'offboarding_id' => $row->id,
                    'title' => $item->title,
                    'description' => $item->description,
                    'kind' => $item->kind ?: 'checklist',
                    'owner' => $item->owner ?: 'hr',
                    'status' => 'pending',
                    'required' => $item->required,
                    'sort_order' => $item->sort_order,
                ]);
            }

            return $row->load('tasks');
        });

        HrmNotifier::notify(
            $employee->id,
            'offboarding_started',
            'فرایند تسویه و خروج',
            'فرایند تسویه حساب و خروج شما آغاز شد. وضعیت کارها را در پورتال من ببینید.'
        );

        return $row;
    }

    public function refreshProgress(HrmOffboarding $offboarding): HrmOffboarding
    {
        $offboarding->load('tasks');
        if ($offboarding->status === 'cancelled') {
            return $offboarding;
        }
        $open = $offboarding->tasks->contains(
            fn (HrmOffboardingTask $task) => $task->required && ! in_array($task->status, ['done', 'skipped'], true)
        );
        $offboarding->status = $open ? 'in_progress' : 'completed';
        $offboarding->completed_at = $open ? null : ($offboarding->completed_at ?? now());
        $offboarding->save();

        if (! $open) {
            $employee = $offboarding->employee;
            if ($employee && $employee->status !== 'terminated') {
                $employee->update(['status' => 'terminated']);
            }
        }

        return $offboarding;
    }
}

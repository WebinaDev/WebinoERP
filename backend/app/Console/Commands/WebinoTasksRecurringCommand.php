<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Projects\Entities\PrjTaskTemplate;
use Modules\Projects\Entities\ProjectTask;
use Modules\Projects\Entities\WorkflowStatus;

class WebinoTasksRecurringCommand extends Command
{
    protected $signature = 'webino:tasks:recurring';

    protected $description = 'Materialize recurring tasks from PrjTaskTemplate schedules (parity webinocrm check_recurring_tasks)';

    public function handle(): int
    {
        $now = now();
        PrjTaskTemplate::query()
            ->whereNotNull('schedule')
            ->where(function ($q) use ($now) {
                $q->whereNull('next_run_at')->orWhere('next_run_at', '<=', $now);
            })
            ->orderBy('id')
            ->each(function (PrjTaskTemplate $tpl) use ($now) {
                if (! $this->scheduleMatches($tpl, $now)) {
                    return;
                }

                $payload = $tpl->payload ?? [];
                $checklist = ($tpl->copy_checklists ?? true) ? ($payload['checklist'] ?? []) : [];
                $assignee = ($tpl->copy_assignees ?? false) ? ($payload['assignee_id'] ?? null) : null;
                $ws = $payload['workflow_status_id'] ?? WorkflowStatus::query()->orderBy('sort_order')->value('id');

                ProjectTask::query()->create([
                    'project_id' => $tpl->project_id,
                    'title' => $tpl->title,
                    'content' => $tpl->description,
                    'status' => 'open',
                    'workflow_status_id' => $ws,
                    'assignee_id' => $assignee,
                    'checklist' => is_array($checklist) ? $checklist : [],
                    'created_by' => $tpl->created_by,
                ]);

                $tpl->update(['next_run_at' => $this->computeNextRun($tpl, $now)]);
            });

        $this->info('Recurring task sweep done.');

        return self::SUCCESS;
    }

    protected function scheduleMatches(PrjTaskTemplate $tpl, Carbon $now): bool
    {
        $s = $tpl->schedule ?? [];
        $freq = $s['frequency'] ?? 'daily';
        if ($freq === 'daily') {
            return true;
        }
        if ($freq === 'weekly') {
            $dow = (int) ($s['weekday'] ?? 1);

            return (int) $now->dayOfWeekIso === $dow;
        }
        if ($freq === 'monthly') {
            $dom = (int) ($s['day_of_month'] ?? $now->day);

            return (int) $now->day === $dom;
        }

        return true;
    }

    protected function computeNextRun(PrjTaskTemplate $tpl, Carbon $now): ?Carbon
    {
        $s = $tpl->schedule ?? [];
        $freq = $s['frequency'] ?? 'daily';
        if ($freq === 'daily') {
            return $now->copy()->addDay()->startOfDay();
        }
        if ($freq === 'weekly') {
            return $now->copy()->addWeek()->startOfDay();
        }
        if ($freq === 'monthly') {
            return $now->copy()->addMonthNoOverflow()->startOfDay();
        }

        return $now->copy()->addDay();
    }
}

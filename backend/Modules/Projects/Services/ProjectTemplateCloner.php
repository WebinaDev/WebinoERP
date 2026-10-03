<?php

namespace Modules\Projects\Services;

use App\Support\MutationAudit;
use Illuminate\Support\Facades\DB;
use Modules\Projects\Entities\PrjMilestone;
use Modules\Projects\Entities\Project;
use Modules\Projects\Entities\ProjectTask;

class ProjectTemplateCloner
{
    public function instantiate(Project $template, string $name, ?int $userId, ?int $accountId = null): Project
    {
        abort_unless($template->is_template, 422, 'Project is not a template');

        return DB::transaction(function () use ($template, $name, $userId, $accountId) {
            $project = Project::query()->create([
                'name' => $name,
                'description' => $template->description,
                'status' => 'active',
                'customer_account_id' => $accountId,
                'created_by' => $userId,
                'manager_user_id' => $template->manager_user_id,
                'is_template' => false,
                'budget_amount' => $template->budget_amount,
                'budget_hours' => $template->budget_hours,
                'hourly_rate' => $template->hourly_rate,
            ]);

            foreach ($template->tasks()->orderBy('id')->get() as $task) {
                ProjectTask::query()->create([
                    'project_id' => $project->id,
                    'title' => $task->title,
                    'status' => 'open',
                    'priority' => $task->priority,
                    'content' => $task->content,
                    'checklist' => $task->checklist,
                    'estimate_hours' => $task->estimate_hours,
                    'created_by' => $userId,
                ]);
            }

            foreach ($template->milestones()->orderBy('id')->get() as $milestone) {
                PrjMilestone::query()->create([
                    'project_id' => $project->id,
                    'title' => $milestone->title,
                    'status' => 'open',
                ]);
            }

            MutationAudit::record($userId, 'projects', 'template.instantiate', Project::class, $project->id, [
                'template_id' => $template->id,
            ]);

            return $project->load('tasks');
        });
    }
}

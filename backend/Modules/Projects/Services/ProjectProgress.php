<?php

namespace Modules\Projects\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Projects\Entities\Project;

class ProjectProgress
{
    /**
     * @param  iterable<Project>  $projects
     */
    public function decorate(iterable $projects): void
    {
        $projects = $projects instanceof Collection ? $projects : collect($projects);
        $ids = $projects->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
        if ($ids === []) {
            return;
        }

        $tasks = DB::table('prj_tasks')
            ->whereIn('project_id', $ids)
            ->selectRaw("project_id, count(*) as total, sum(case when status in ('done', 'completed') then 1 else 0 end) as done")
            ->groupBy('project_id')
            ->get()
            ->keyBy('project_id');

        $milestones = Schema::hasTable('prj_milestones')
            ? DB::table('prj_milestones')
                ->whereIn('project_id', $ids)
                ->selectRaw("project_id, count(*) as total, sum(case when status = 'done' then 1 else 0 end) as done")
                ->groupBy('project_id')
                ->get()
                ->keyBy('project_id')
            : collect();

        foreach ($projects as $project) {
            $taskRow = $tasks[$project->id] ?? null;
            $mileRow = $milestones[$project->id] ?? null;
            $total = (int) ($taskRow->total ?? 0) + (int) ($mileRow->total ?? 0);
            $done = (int) ($taskRow->done ?? 0) + (int) ($mileRow->done ?? 0);
            $percent = $total === 0
                ? ($project->status === 'completed' ? 100 : 0)
                : (int) round(100 * $done / $total);
            $project->setAttribute('progress_percent', $percent);
        }
    }
}

<?php

namespace Modules\Projects\Policies;

use App\Models\User;
use Modules\Projects\Entities\ProjectTask;

class ProjectTaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('projects.tasks.view');
    }

    public function view(User $user, ProjectTask $task): bool
    {
        return $user->can('projects.tasks.view');
    }

    public function create(User $user): bool
    {
        return $user->can('projects.tasks.manage');
    }

    public function update(User $user, ProjectTask $task): bool
    {
        return $user->can('projects.tasks.manage');
    }

    public function delete(User $user, ProjectTask $task): bool
    {
        return $user->can('projects.tasks.manage');
    }
}

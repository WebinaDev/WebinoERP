<?php

namespace Modules\Projects\Policies;

use App\Models\User;
use Modules\Projects\Entities\Project;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('projects.projects.view');
    }

    public function view(User $user, Project $project): bool
    {
        return $user->can('projects.projects.view');
    }

    public function create(User $user): bool
    {
        return $user->can('projects.projects.manage');
    }

    public function update(User $user, Project $project): bool
    {
        return $user->can('projects.projects.manage');
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->can('projects.projects.manage');
    }
}

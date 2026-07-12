<?php

namespace Modules\Projects\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'project_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(PrjTicket::class, 'project_id');
    }

    protected $table = 'prj_projects';

    protected $fillable = [
        'name',
        'description',
        'status',
        'customer_account_id',
        'created_by',
        'is_template',
    ];

    protected function casts(): array
    {
        return [
            'is_template' => 'boolean',
        ];
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class, 'project_id');
    }

    public function sprints(): HasMany
    {
        return $this->hasMany(PrjSprint::class, 'project_id');
    }
}

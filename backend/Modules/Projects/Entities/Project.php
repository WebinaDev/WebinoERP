<?php

namespace Modules\Projects\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Crm\Entities\CrmAccount;

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
        'manager_user_id',
        'start_date',
        'due_date',
        'is_template',
        'budget_amount',
        'budget_hours',
        'hourly_rate',
    ];

    protected function casts(): array
    {
        return [
            'is_template' => 'boolean',
            'start_date' => 'date',
            'due_date' => 'date',
            'budget_amount' => 'decimal:2',
            'budget_hours' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CrmAccount::class, 'customer_account_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(PrjMilestone::class, 'project_id');
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

<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrmOrgPosition extends Model
{
    protected $table = 'hrm_org_positions';

    protected $fillable = [
        'code',
        'title',
        'department_id',
        'parent_position_id',
        'is_approved_quota',
        'job_grade',
        'status',
    ];

    protected $casts = [
        'is_approved_quota' => 'boolean',
        'job_grade' => 'integer',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(HrmDepartment::class, 'department_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_position_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_position_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(HrmOrgStructureHistory::class, 'position_id');
    }
}

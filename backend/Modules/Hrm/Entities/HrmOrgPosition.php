<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmOrgPosition extends Model
{
    protected $table = 'hrm_org_positions';

    protected $fillable = ['title', 'department', 'parent_id', 'sort_order', 'is_active', 'incumbent_employee_id'];

    public function incumbent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'incumbent_employee_id');
    }

    public function successors(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(HrmSuccessionPlan::class, 'position_id');
    }

    protected $casts = ['is_active' => 'boolean'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }
}

<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmSuccessionPlan extends Model
{
    protected $table = 'hrm_succession_plans';

    protected $fillable = ['position_id', 'successor_employee_id', 'readiness', 'is_primary', 'notes'];

    protected $casts = ['is_primary' => 'boolean'];

    public function position(): BelongsTo
    {
        return $this->belongsTo(HrmOrgPosition::class, 'position_id');
    }

    public function successor(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'successor_employee_id');
    }
}

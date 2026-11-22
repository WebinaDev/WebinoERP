<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmOrgStructureHistory extends Model
{
    protected $table = 'hrm_org_structure_histories';

    protected $fillable = [
        'position_id',
        'employee_id',
        'effective_from',
        'effective_to',
        'change_type',
        'decree_no',
        'change_reason',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function position(): BelongsTo
    {
        return $this->belongsTo(HrmOrgPosition::class, 'position_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }
}

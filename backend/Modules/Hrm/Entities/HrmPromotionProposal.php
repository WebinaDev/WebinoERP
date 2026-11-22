<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmPromotionProposal extends Model
{
    protected $table = 'hrm_promotion_proposals';

    protected $fillable = [
        'employee_id',
        'current_career_path_id',
        'target_career_path_id',
        'proposal_source',
        'eligibility_score',
        'system_justification',
        'status',
        'decree_id',
    ];

    protected $casts = [
        'eligibility_score' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(HrmEmployee::class, 'employee_id');
    }

    public function targetCareerPath(): BelongsTo
    {
        return $this->belongsTo(HrmCareerPath::class, 'target_career_path_id');
    }

    public function decree(): BelongsTo
    {
        return $this->belongsTo(HrmEmploymentDecree::class, 'decree_id');
    }
}

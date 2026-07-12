<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmInterview extends Model
{
    protected $table = 'hrm_interviews';

    protected $fillable = ['applicant_id', 'scheduled_at', 'interviewer', 'status', 'notes'];

    protected $casts = ['scheduled_at' => 'datetime'];

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(HrmJobApplicant::class, 'applicant_id');
    }
}

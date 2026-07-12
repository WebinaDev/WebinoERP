<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HrmJobApplicant extends Model
{
    protected $table = 'hrm_job_applicants';

    protected $fillable = [
        'job_posting_id', 'first_name', 'last_name', 'email', 'mobile', 'status', 'resume_notes',
    ];

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(HrmJobPosting::class, 'job_posting_id');
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(HrmInterview::class, 'applicant_id');
    }
}

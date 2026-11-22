<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmResumeAiMatch extends Model
{
    protected $table = 'hrm_resume_ai_matches';

    protected $fillable = [
        'applicant_id',
        'posting_id',
        'overall_score',
        'skills_match_score',
        'experience_match_score',
        'education_match_score',
        'matched_skills',
        'missing_skills',
        'ai_summary_fa',
        'recommendation',
    ];

    protected $casts = [
        'overall_score' => 'decimal:2',
        'skills_match_score' => 'decimal:2',
        'experience_match_score' => 'decimal:2',
        'education_match_score' => 'decimal:2',
        'matched_skills' => 'array',
        'missing_skills' => 'array',
    ];

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(HrmJobApplicant::class, 'applicant_id');
    }

    public function posting(): BelongsTo
    {
        return $this->belongsTo(HrmJobPosting::class, 'posting_id');
    }
}

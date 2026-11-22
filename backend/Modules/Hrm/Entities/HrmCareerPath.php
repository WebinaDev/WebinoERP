<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;

class HrmCareerPath extends Model
{
    protected $table = 'hrm_career_paths';

    protected $fillable = [
        'job_family',
        'level',
        'title_fa',
        'min_tenure_months',
        'min_performance_score',
        'required_skills',
        'required_certifications',
        'base_salary_multiplier',
    ];

    protected $casts = [
        'required_skills' => 'array',
        'required_certifications' => 'array',
        'base_salary_multiplier' => 'decimal:2',
        'min_performance_score' => 'decimal:2',
    ];
}

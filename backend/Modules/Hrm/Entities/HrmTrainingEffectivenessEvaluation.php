<?php

namespace Modules\Hrm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HrmTrainingEffectivenessEvaluation extends Model
{
    protected $table = 'hrm_training_effectiveness_evaluations';

    protected $fillable = [
        'enrollment_id',
        'kirkpatrick_level',
        'score',
        'manager_feedback',
        'kpi_impact_summary',
        'evaluation_date',
    ];

    protected $casts = [
        'score' => 'decimal:2',
        'evaluation_date' => 'date',
    ];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(HrmTrainingEnrollment::class, 'enrollment_id');
    }
}

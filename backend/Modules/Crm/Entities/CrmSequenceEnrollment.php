<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmSequenceEnrollment extends Model
{
    protected $table = 'crm_sequence_enrollments';

    protected $fillable = [
        'sequence_id', 'related_type', 'related_id', 'step_index', 'next_run_at', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return ['next_run_at' => 'datetime'];
    }

    public function sequence(): BelongsTo
    {
        return $this->belongsTo(CrmSequence::class, 'sequence_id');
    }
}

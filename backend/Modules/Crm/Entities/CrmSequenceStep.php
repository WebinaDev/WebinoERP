<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmSequenceStep extends Model
{
    protected $table = 'crm_sequence_steps';

    protected $fillable = ['sequence_id', 'template_id', 'delay_days', 'sort_order'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(CrmMessageTemplate::class, 'template_id');
    }
}

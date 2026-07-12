<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmStage extends Model
{
    protected $table = 'crm_stages';

    protected $fillable = ['pipeline_id', 'name', 'description', 'sort_order', 'probability', 'color', 'is_closed', 'is_won'];

    protected $casts = [
        'is_closed' => 'boolean',
        'is_won' => 'boolean',
    ];

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(CrmPipeline::class, 'pipeline_id');
    }
}

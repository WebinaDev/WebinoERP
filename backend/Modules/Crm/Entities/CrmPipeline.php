<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrmPipeline extends Model
{
    protected $table = 'crm_pipelines';

    protected $fillable = ['name', 'description', 'is_default', 'is_active', 'created_by'];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function stages(): HasMany
    {
        return $this->hasMany(CrmStage::class, 'pipeline_id')->orderBy('sort_order');
    }
}

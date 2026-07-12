<?php

namespace Modules\Mfg\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MfgQualityCheckItem extends Model
{
    protected $table = 'mfg_quality_check_items';

    protected $fillable = ['inspection_id', 'criterion', 'measured_value', 'spec_min', 'spec_max', 'passed'];

    protected function casts(): array
    {
        return [
            'spec_min' => 'decimal:4',
            'spec_max' => 'decimal:4',
            'passed' => 'boolean',
        ];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(MfgQualityInspection::class, 'inspection_id');
    }
}

<?php

namespace Modules\Mfg\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MfgQualityInspection extends Model
{
    protected $table = 'mfg_quality_inspections';

    protected $fillable = ['work_order_id', 'type', 'status', 'result', 'inspected_at'];

    protected function casts(): array
    {
        return ['inspected_at' => 'datetime'];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(MfgWorkOrder::class, 'work_order_id');
    }

    public function checkItems(): HasMany
    {
        return $this->hasMany(MfgQualityCheckItem::class, 'inspection_id');
    }
}

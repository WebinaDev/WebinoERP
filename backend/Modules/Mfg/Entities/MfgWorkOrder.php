<?php

namespace Modules\Mfg\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MfgWorkOrder extends Model
{
    protected $table = 'mfg_work_orders';

    protected $fillable = [
        'bom_id', 'product_id', 'qty_planned', 'qty_produced', 'status', 'due_at', 'warehouse_id',
    ];

    protected function casts(): array
    {
        return [
            'qty_planned' => 'decimal:4',
            'qty_produced' => 'decimal:4',
            'due_at' => 'datetime',
        ];
    }

    public function bom(): BelongsTo
    {
        return $this->belongsTo(MfgBom::class, 'bom_id');
    }

    public function operations(): HasMany
    {
        return $this->hasMany(MfgWorkOrderOperation::class, 'work_order_id')->orderBy('sequence');
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(MfgQualityInspection::class, 'work_order_id');
    }
}

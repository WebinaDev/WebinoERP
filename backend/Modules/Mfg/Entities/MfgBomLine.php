<?php

namespace Modules\Mfg\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MfgBomLine extends Model
{
    protected $table = 'mfg_bom_lines';

    protected $fillable = ['bom_id', 'component_product_id', 'quantity', 'unit', 'scrap_percent'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'scrap_percent' => 'decimal:2',
        ];
    }

    public function bom(): BelongsTo
    {
        return $this->belongsTo(MfgBom::class, 'bom_id');
    }
}

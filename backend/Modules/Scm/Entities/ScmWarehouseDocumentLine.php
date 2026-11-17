<?php

namespace Modules\Scm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScmWarehouseDocumentLine extends Model
{
    protected $table = 'scm_warehouse_document_lines';

    protected $fillable = [
        'document_id', 'product_id', 'location_id', 'lot_batch_no',
        'serial_no', 'expiry_date', 'quantity', 'uom', 'unit_cost', 'total_cost',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'quantity' => 'decimal:4',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(ScmWarehouseDocument::class, 'document_id');
    }
}

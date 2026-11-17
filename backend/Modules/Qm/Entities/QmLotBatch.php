<?php

namespace Modules\Qm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Entities\AccProduct;

class QmLotBatch extends Model
{
    protected $table = 'qm_lot_batches';

    protected $fillable = [
        'lot_number',
        'product_id',
        'quantity',
        'manufacture_date',
        'expiry_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'manufacture_date' => 'date',
        'expiry_date' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(AccProduct::class, 'product_id');
    }
}

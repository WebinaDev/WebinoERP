<?php

namespace Modules\Scm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Entities\AccProduct;

class ScmWarehouseStock extends Model
{
    protected $table = 'scm_warehouse_stock';

    protected $fillable = [
        'warehouse_id', 'product_id', 'quantity', 'reorder_point',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'reorder_point' => 'decimal:4',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(ScmWarehouse::class, 'warehouse_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(AccProduct::class, 'product_id');
    }
}

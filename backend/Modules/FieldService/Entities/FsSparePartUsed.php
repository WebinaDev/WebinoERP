<?php

namespace Modules\FieldService\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Entities\AccProduct;
use Modules\Scm\Entities\ScmWarehouse;

class FsSparePartUsed extends Model
{
    protected $table = 'fs_spare_parts_used';

    protected $fillable = [
        'service_order_id',
        'warehouse_id',
        'product_id',
        'quantity',
        'unit_cost',
        'total_cost',
        'is_warranty_covered',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'is_warranty_covered' => 'boolean',
    ];

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(FsServiceOrder::class, 'service_order_id');
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

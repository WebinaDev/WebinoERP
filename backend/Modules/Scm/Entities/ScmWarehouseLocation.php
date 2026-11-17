<?php

namespace Modules\Scm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScmWarehouseLocation extends Model
{
    protected $table = 'scm_warehouse_locations';

    protected $fillable = [
        'warehouse_id', 'name', 'barcode', 'location_type',
        'zone', 'aisle', 'rack', 'bin', 'is_active',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(ScmWarehouse::class, 'warehouse_id');
    }
}

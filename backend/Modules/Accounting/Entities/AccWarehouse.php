<?php

namespace Modules\Accounting\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccWarehouse extends Model
{
    protected $table = 'acc_warehouses';

    protected $fillable = ['name', 'code', 'description', 'address', 'location', 'is_default', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function stockLines(): HasMany
    {
        return $this->hasMany(AccWarehouseStock::class, 'warehouse_id');
    }
}

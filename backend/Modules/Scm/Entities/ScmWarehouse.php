<?php

namespace Modules\Scm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScmWarehouse extends Model
{
    protected $table = 'scm_warehouses';

    protected $fillable = ['name', 'address', 'is_default', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function stockLines(): HasMany
    {
        return $this->hasMany(ScmWarehouseStock::class, 'warehouse_id');
    }
}

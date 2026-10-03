<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;

class CrmCatalogProduct extends Model
{
    protected $table = 'crm_catalog_products';

    protected $fillable = [
        'company_id', 'sku', 'name', 'description', 'unit', 'tax_percent', 'active',
    ];

    protected function casts(): array
    {
        return [
            'tax_percent' => 'decimal:2',
            'active' => 'boolean',
        ];
    }
}

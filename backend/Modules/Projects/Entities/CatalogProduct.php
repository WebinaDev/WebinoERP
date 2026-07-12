<?php

namespace Modules\Projects\Entities;

use Illuminate\Database\Eloquent\Model;

class CatalogProduct extends Model
{
    protected $table = 'prj_products';

    protected $fillable = [
        'name', 'sku', 'price', 'task_template_id', 'service_task_type', 'task_template',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'task_template' => 'array',
        ];
    }
}

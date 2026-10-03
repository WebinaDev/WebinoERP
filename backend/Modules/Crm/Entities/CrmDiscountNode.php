<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;

class CrmDiscountNode extends Model
{
    protected $table = 'crm_discount_nodes';

    protected $fillable = [
        'parent_id', 'scope', 'scope_key', 'name', 'percent', 'min_amount', 'stackable',
    ];

    protected function casts(): array
    {
        return [
            'percent' => 'float',
            'min_amount' => 'float',
            'stackable' => 'boolean',
        ];
    }
}

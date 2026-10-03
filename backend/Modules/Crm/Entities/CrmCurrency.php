<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;

class CrmCurrency extends Model
{
    protected $table = 'crm_currencies';

    protected $fillable = [
        'code', 'name', 'symbol', 'decimal_places', 'is_base',
    ];

    protected function casts(): array
    {
        return [
            'is_base' => 'boolean',
        ];
    }
}

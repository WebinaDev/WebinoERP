<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;

class CrmFxRate extends Model
{
    protected $table = 'crm_fx_rates';

    protected $fillable = [
        'base_code', 'quote_code', 'rate', 'effective_on',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:8',
            'effective_on' => 'date',
        ];
    }
}

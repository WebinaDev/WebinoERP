<?php

namespace Modules\Sales\Entities;

use Illuminate\Database\Eloquent\Model;

class SalesRahnStatement extends Model
{
    protected $table = 'sales_rahn_statements';

    protected $fillable = [
        'contract_id',
        'quote_id',
        'year_month',
        'G',
        'R',
        'D',
        'X',
        'S',
        'F',
        'p',
        'V',
        'C',
        'Pi',
        'invoice_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'contract_id' => 'integer',
        'quote_id' => 'integer',
        'G' => 'float',
        'R' => 'float',
        'D' => 'float',
        'X' => 'float',
        'S' => 'float',
        'F' => 'float',
        'p' => 'float',
        'V' => 'float',
        'C' => 'float',
        'Pi' => 'float',
        'invoice_id' => 'integer',
    ];
}

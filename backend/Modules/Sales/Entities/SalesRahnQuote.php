<?php

namespace Modules\Sales\Entities;

use Illuminate\Database\Eloquent\Model;

class SalesRahnQuote extends Model
{
    protected $table = 'sales_rahn_quotes';

    protected $fillable = [
        'token',
        'title',
        'status',
        'customer_id',
        'lead_id',
        'contract_id',
        'selected_ids',
        'items_snapshot',
        'calc_snapshot',
        's_hat',
        'duration',
        'mode',
        'p_wanted',
        'f_wanted',
        'F',
        'p',
        'locked_at',
        'clause',
        'created_by',
    ];

    protected $casts = [
        'selected_ids' => 'array',
        'items_snapshot' => 'array',
        'calc_snapshot' => 'array',
        's_hat' => 'float',
        'duration' => 'integer',
        'p_wanted' => 'float',
        'f_wanted' => 'float',
        'F' => 'float',
        'p' => 'float',
        'locked_at' => 'datetime',
        'customer_id' => 'integer',
        'lead_id' => 'integer',
        'contract_id' => 'integer',
    ];
}

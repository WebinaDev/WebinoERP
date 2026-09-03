<?php

namespace Modules\Integrations\Entities;

use Illuminate\Database\Eloquent\Model;

class ModirPayamakTariff extends Model
{
    protected $table = 'modirpayamak_tariffs';

    protected $fillable = [
        'line_type',
        'operator',
        'rate_fa',
        'rate_la',
        'sort',
        'status',
    ];

    protected $casts = [
        'rate_fa' => 'float',
        'rate_la' => 'float',
        'sort' => 'integer',
    ];
}

<?php

namespace Modules\Sales\Entities;

use Illuminate\Database\Eloquent\Model;

class SalesRahnSetting extends Model
{
    protected $table = 'sales_rahn_settings';

    protected $fillable = ['payload'];

    protected $casts = [
        'payload' => 'array',
    ];
}

<?php

namespace Modules\Sales\Entities;

use Illuminate\Database\Eloquent\Model;

class SalesCampaign extends Model
{
    protected $table = 'sales_campaigns';

    protected $fillable = ['name', 'description', 'status', 'starts_at', 'ends_at', 'created_by'];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];
}

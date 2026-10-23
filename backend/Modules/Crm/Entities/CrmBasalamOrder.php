<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;

/** Mirror of a Basalam vendor order pushed by the tenant site plugin (no Basalam order table exists upstream). */
class CrmBasalamOrder extends Model
{
    protected $table = 'crm_basalam_orders';

    protected $fillable = [
        'connection_id', 'crm_account_id', 'site_domain', 'external_id', 'status', 'total', 'buyer_name',
        'buyer_mobile', 'items_count', 'items', 'ordered_at', 'paid_at',
    ];

    protected function casts(): array
    {
        return ['items' => 'array', 'ordered_at' => 'datetime', 'paid_at' => 'datetime', 'total' => 'decimal:2'];
    }
}

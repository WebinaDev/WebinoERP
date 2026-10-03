<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;

class CrmTouchpoint extends Model
{
    protected $table = 'crm_touchpoints';

    protected $fillable = [
        'lead_id', 'deal_id', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'channel', 'occurred_at',
    ];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }
}

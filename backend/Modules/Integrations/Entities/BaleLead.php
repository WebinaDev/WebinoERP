<?php

namespace Modules\Integrations\Entities;

use Illuminate\Database\Eloquent\Model;

class BaleLead extends Model
{
    protected $fillable = [
        'chat_id', 'funnel_stage', 'score', 'last_event_at', 'converted_customer_id',
    ];

    protected function casts(): array
    {
        return [
            'last_event_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}

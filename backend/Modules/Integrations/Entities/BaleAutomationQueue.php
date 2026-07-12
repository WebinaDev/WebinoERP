<?php

namespace Modules\Integrations\Entities;

use Illuminate\Database\Eloquent\Model;

class BaleAutomationQueue extends Model
{
    protected $table = 'bale_automation_queue';

    protected $fillable = [
        'chat_id', 'trigger_key', 'step_key', 'scheduled_for', 'status', 'payload',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'payload' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}

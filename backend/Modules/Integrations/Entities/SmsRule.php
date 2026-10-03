<?php

namespace Modules\Integrations\Entities;

use Illuminate\Database\Eloquent\Model;

class SmsRule extends Model
{
    protected $table = 'int_sms_rules';

    protected $fillable = [
        'name', 'event_key', 'min_priority', 'enabled',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }
}

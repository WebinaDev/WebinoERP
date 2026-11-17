<?php

namespace Modules\Integrations\Entities;

use Illuminate\Database\Eloquent\Model;

class CommunicationTemplate extends Model
{
    protected $table = 'communication_templates';

    protected $fillable = [
        'event_key', 'channel', 'subject', 'body_template', 'sms_pattern_code', 'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];
}

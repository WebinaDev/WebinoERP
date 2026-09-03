<?php

namespace Modules\Integrations\Entities;

use Illuminate\Database\Eloquent\Model;

class ModirPayamakSecretary extends Model
{
    protected $table = 'modirpayamak_secretaries';

    protected $fillable = [
        'domain',
        'type',
        'name',
        'keywords',
        'reply_body',
        'pattern_code',
        'forward_to',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];
}

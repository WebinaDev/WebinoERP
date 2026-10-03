<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;

class CrmMessage extends Model
{
    protected $table = 'crm_messages';

    protected $fillable = [
        'channel', 'template_id', 'related_type', 'related_id', 'to_address',
        'subject', 'body', 'status', 'provider', 'error', 'created_by',
    ];
}

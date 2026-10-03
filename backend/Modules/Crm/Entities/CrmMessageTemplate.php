<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;

class CrmMessageTemplate extends Model
{
    protected $table = 'crm_message_templates';

    protected $fillable = ['channel', 'name', 'subject', 'body', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}

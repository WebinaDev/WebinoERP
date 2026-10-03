<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;

class ContentItem extends Model
{
    protected $table = 'crm_content_items';

    protected $fillable = [
        'calendar_id', 'title', 'body', 'status', 'assignee_id', 'publish_start', 'publish_end', 'remind_at', 'reminded_at',
    ];

    protected function casts(): array
    {
        return [
            'publish_start' => 'datetime',
            'publish_end' => 'datetime',
            'remind_at' => 'datetime',
            'reminded_at' => 'datetime',
        ];
    }
}

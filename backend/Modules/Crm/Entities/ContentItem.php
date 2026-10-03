<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentItem extends Model
{
    protected $table = 'crm_content_items';

    protected $fillable = [
        'calendar_id', 'title', 'body', 'status', 'assignee_id', 'publish_start', 'publish_end', 'remind_at', 'reminded_at',
        'publish_status', 'external_post_id', 'publish_error', 'published_at', 'image_url', 'publish_attempts',
    ];

    protected function casts(): array
    {
        return [
            'publish_start' => 'datetime',
            'publish_end' => 'datetime',
            'remind_at' => 'datetime',
            'reminded_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(ContentCalendar::class, 'calendar_id');
    }
}

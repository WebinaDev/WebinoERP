<?php

namespace Modules\AiContent\Entities;

use Illuminate\Database\Eloquent\Model;

class AiCalendarSlot extends Model
{
    protected $table = 'ai_content_calendar';

    protected $fillable = ['slot_date', 'content_type', 'topic', 'focus_keyword', 'secondary_keywords', 'category_id', 'product_id', 'status', 'job_id', 'notes'];

    protected $casts = [
        'slot_date' => 'date',
        'category_id' => 'integer',
        'product_id' => 'integer',
        'job_id' => 'integer',
    ];
}

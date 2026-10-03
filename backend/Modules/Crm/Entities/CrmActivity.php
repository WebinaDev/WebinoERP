<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrmActivity extends Model
{
    use SoftDeletes;

    protected $table = 'crm_activities';

    protected $fillable = [
        'type', 'subject', 'description', 'related_model', 'related_id', 'outcome',
        'scheduled_at', 'completed_at', 'due_date', 'remind_at', 'reminded_at', 'meta',
        'priority', 'assigned_to', 'created_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
        'due_date' => 'date',
        'remind_at' => 'datetime',
        'reminded_at' => 'datetime',
        'meta' => 'array',
    ];
}

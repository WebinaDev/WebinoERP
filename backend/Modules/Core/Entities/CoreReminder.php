<?php

namespace Modules\Core\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CoreReminder extends Model
{
    protected $table = 'core_reminders';

    protected $fillable = [
        'user_id',
        'remindable_type',
        'remindable_id',
        'title',
        'body',
        'channel',
        'payload',
        'remind_at',
        'sent_at',
        'snoozed_until',
        'dismissed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'remind_at' => 'datetime',
            'sent_at' => 'datetime',
            'snoozed_until' => 'datetime',
            'dismissed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function remindable(): MorphTo
    {
        return $this->morphTo();
    }
}

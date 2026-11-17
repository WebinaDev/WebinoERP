<?php

namespace Modules\Bi\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiThresholdAlert extends Model
{
    protected $table = 'bi_threshold_alerts';

    protected $fillable = [
        'title',
        'metric_key',
        'comparator',
        'threshold_value',
        'severity',
        'notify_channels',
        'notify_user_id',
        'is_active',
        'last_triggered_at',
    ];

    protected $casts = [
        'threshold_value' => 'decimal:2',
        'notify_channels' => 'array',
        'is_active' => 'boolean',
        'last_triggered_at' => 'datetime',
    ];

    public function notifyUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'notify_user_id');
    }
}

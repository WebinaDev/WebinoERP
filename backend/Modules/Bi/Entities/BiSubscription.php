<?php

namespace Modules\Bi\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiSubscription extends Model
{
    protected $table = 'bi_subscriptions';

    protected $fillable = [
        'name',
        'report_type',
        'frequency',
        'format',
        'recipients',
        'filters',
        'is_active',
        'last_sent_at',
        'created_by',
    ];

    protected $casts = [
        'recipients' => 'array',
        'filters' => 'array',
        'is_active' => 'boolean',
        'last_sent_at' => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

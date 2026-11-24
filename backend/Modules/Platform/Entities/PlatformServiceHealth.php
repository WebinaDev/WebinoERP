<?php

namespace Modules\Platform\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformServiceHealth extends Model
{
    protected $table = 'platform_service_health';

    protected $fillable = [
        'server_id',
        'service_key',
        'status',
        'message',
        'meta',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'checked_at' => 'datetime',
        ];
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(PlatformServer::class, 'server_id');
    }
}

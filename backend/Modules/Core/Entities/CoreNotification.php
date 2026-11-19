<?php

namespace Modules\Core\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoreNotification extends Model
{
    protected $table = 'core_notifications';

    protected $fillable = ['user_id', 'type', 'data', 'is_read'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'is_read' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Respect the person's in-app switches for every sender that writes here.
        static::creating(function (self $n): bool {
            if (! $n->user_id) {
                return true;
            }

            return (new \Modules\Core\Services\NotificationPreferences)->allows((int) $n->user_id, 'in_app', (string) ($n->type ?? 'general'));
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

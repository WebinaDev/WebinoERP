<?php

namespace Modules\Core\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoreChatReadReceipt extends Model
{
    protected $table = 'core_chat_read_receipts';

    protected $fillable = [
        'channel_id',
        'user_id',
        'last_read_message_id',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(CoreChatChannel::class, 'channel_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lastReadMessage(): BelongsTo
    {
        return $this->belongsTo(CoreChatMessage::class, 'last_read_message_id');
    }
}

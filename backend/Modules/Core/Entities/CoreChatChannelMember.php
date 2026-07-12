<?php

namespace Modules\Core\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoreChatChannelMember extends Model
{
    protected $table = 'core_chat_channel_members';

    protected $fillable = [
        'channel_id',
        'user_id',
        'role',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
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
}

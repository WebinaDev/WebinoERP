<?php

namespace Modules\Core\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CoreChatMessage extends Model
{
    use SoftDeletes;

    protected $table = 'core_chat_messages';

    protected $fillable = [
        'channel_id',
        'user_id',
        'reply_to',
        'body',
        'attachments',
        'edited_at',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'edited_at' => 'datetime',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(CoreChatChannel::class, 'channel_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'reply_to');
    }
}

<?php

namespace Modules\Core\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CoreChatChannel extends Model
{
    protected $table = 'core_chat_channels';

    protected $fillable = [
        'name',
        'type',
        'created_by',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(CoreChatChannelMember::class, 'channel_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CoreChatMessage::class, 'channel_id');
    }

    public function readReceipts(): HasMany
    {
        return $this->hasMany(CoreChatReadReceipt::class, 'channel_id');
    }

    /**
     * Channels the user can see: public rooms + any channel they are a member of.
     */
    public function scopeAccessibleForUser(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->where('type', 'public')
                ->orWhereHas('members', fn (Builder $m) => $m->where('user_id', $user->id));
        });
    }

    public function userIsMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->exists();
    }

    public function userCanParticipate(User $user): bool
    {
        if ($this->type === 'public') {
            return true;
        }

        return $this->userIsMember($user);
    }

    public function userCanView(User $user): bool
    {
        if ($this->type === 'public') {
            return true;
        }

        return $this->userIsMember($user);
    }
}

<?php

use Illuminate\Support\Facades\Broadcast;
use Modules\Core\Entities\CoreChatChannel;

Broadcast::channel('chat.{channelId}', function ($user, int|string $channelId) {
    $channel = CoreChatChannel::query()->find((int) $channelId);
    if (! $channel) {
        return false;
    }

    return $channel->userCanView($user)
        ? ['id' => $user->id, 'name' => $user->name]
        : false;
});

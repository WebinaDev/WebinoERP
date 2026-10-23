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

// Customer 360 Hub live updates — any user who can open at least one hub section.
Broadcast::channel('crm.customer.{accountId}', function ($user, int|string $accountId) {
    return app(\Modules\Crm\Services\Hub\CustomerHubAccess::class)->sections($user) !== []
        && \Modules\Crm\Entities\CrmAccount::query()->whereKey((int) $accountId)->exists();
});

<?php

namespace Modules\Core\Policies;

use App\Models\User;
use Modules\Core\Entities\CoreChatChannel;

class ChatChannelPolicy
{
    public function view(User $user, CoreChatChannel $channel): bool
    {
        return $channel->userCanView($user);
    }

    public function participate(User $user, CoreChatChannel $channel): bool
    {
        return $channel->userCanParticipate($user);
    }

    public function create(User $user): bool
    {
        return true;
    }
}

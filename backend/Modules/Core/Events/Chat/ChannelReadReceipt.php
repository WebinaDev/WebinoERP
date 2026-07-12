<?php

namespace Modules\Core\Events\Chat;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChannelReadReceipt implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $channelId,
        public int $userId,
        public ?int $lastReadMessageId
    ) {}

    /**
     * @return array<int, \Illuminate\Broadcasting\PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('chat.'.$this->channelId)];
    }

    public function broadcastAs(): string
    {
        return 'ChannelReadReceipt';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'channel_id' => $this->channelId,
            'user_id' => $this->userId,
            'last_read_message_id' => $this->lastReadMessageId,
        ];
    }
}

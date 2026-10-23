<?php

namespace Modules\Crm\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/** Live hub refresh over the existing Reverb socket: private-crm.customer.{accountId}. Payload carries no data, only "something changed". */
class CustomerHubUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $accountId, public string $action, public ?int $byUserId = null) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('crm.customer.'.$this->accountId)];
    }

    public function broadcastAs(): string
    {
        return 'CustomerHubUpdated';
    }

    public function broadcastWith(): array
    {
        return ['account_id' => $this->accountId, 'action' => $this->action, 'by' => $this->byUserId, 'at' => now()->toIso8601String()];
    }
}

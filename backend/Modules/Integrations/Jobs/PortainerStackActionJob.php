<?php

namespace Modules\Integrations\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Core\Entities\CoreInfraAuditLog;
use Modules\Integrations\Services\PortainerApiClient;

class PortainerStackActionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ?int $userId,
        public int $stackId,
        public int $endpointId,
        public string $action
    ) {}

    public function handle(): void
    {
        $client = PortainerApiClient::fromCurrentSettings();
        if ($client === null) {
            CoreInfraAuditLog::query()->create([
                'user_id' => $this->userId,
                'channel' => 'portainer',
                'action' => 'stack_'.$this->action.'_skipped',
                'subject_type' => 'stack',
                'subject_id' => (string) $this->stackId,
                'payload' => ['reason' => 'portainer_not_configured'],
            ]);

            return;
        }

        try {
            if ($this->action === 'start') {
                $client->stackStart($this->stackId, $this->endpointId);
            } else {
                $client->stackStop($this->stackId, $this->endpointId);
            }
            CoreInfraAuditLog::query()->create([
                'user_id' => $this->userId,
                'channel' => 'portainer',
                'action' => 'stack_'.$this->action,
                'subject_type' => 'stack',
                'subject_id' => (string) $this->stackId,
                'payload' => ['endpoint_id' => $this->endpointId, 'ok' => true],
            ]);
        } catch (\Throwable $e) {
            CoreInfraAuditLog::query()->create([
                'user_id' => $this->userId,
                'channel' => 'portainer',
                'action' => 'stack_'.$this->action.'_failed',
                'subject_type' => 'stack',
                'subject_id' => (string) $this->stackId,
                'payload' => ['endpoint_id' => $this->endpointId, 'error' => $e->getMessage()],
            ]);
            throw $e;
        }
    }
}

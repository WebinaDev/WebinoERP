<?php

namespace Modules\Core\Observers;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Services\ActivityLogger;

class ActivityObserver
{
    public function __construct(private ActivityLogger $logger) {}

    public function created(Model $model): void
    {
        $this->logger->log('created', $model, null, $this->snapshot($model));
    }

    public function updated(Model $model): void
    {
        $changes = $model->getChanges();
        if ($changes === []) {
            return;
        }

        $this->logger->log('updated', $model, null, [
            'changes' => $changes,
            'dirty' => $model->getDirty(),
        ]);
    }

    public function deleted(Model $model): void
    {
        $this->logger->log('deleted', $model, null, $this->snapshot($model));
    }

    /**
     * @return array<string,mixed>
     */
    private function snapshot(Model $model): array
    {
        return [
            'id' => $model->getKey(),
            'attributes' => $model->getAttributes(),
        ];
    }
}

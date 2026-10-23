<?php

namespace Modules\Crm\Events\Customer;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Base for cross-module customer sync events (PLAN §4.4). Carries scalars only so it
 * serializes cleanly onto the queue. Subclasses set MODULE / TYPE / SUBJECT / TITLE.
 */
abstract class CustomerActivityEvent
{
    use Dispatchable;

    public const MODULE = 'crm';

    public const TYPE = 'customer.event';

    public const SUBJECT = null;

    public const TITLE = '';

    public function __construct(
        public int $accountId,
        public ?int $subjectId = null,
        public array $meta = [],
        public ?int $actorId = null,
        public ?string $occurredAt = null,
        public ?string $title = null,
    ) {}

    public function activity(): array
    {
        return [
            'account_id' => $this->accountId, 'module' => $this->meta['module'] ?? static::MODULE, 'type' => static::TYPE,
            'subject_type' => $this->meta['subject_type'] ?? static::SUBJECT, 'subject_id' => $this->subjectId,
            'title' => $this->title ?: static::TITLE, 'meta' => $this->meta, 'actor_id' => $this->actorId,
            'occurred_at' => $this->occurredAt,
        ];
    }
}

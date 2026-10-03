<?php

namespace Modules\Projects\Support;

use Illuminate\Validation\ValidationException;

class StatusMachine
{
    /** @var array<string, array<string, list<string>>> */
    private const TRANSITIONS = [
        'project' => [
            'draft' => ['active', 'cancelled'],
            'active' => ['on_hold', 'completed', 'cancelled'],
            'on_hold' => ['active', 'completed', 'cancelled'],
            'completed' => ['active'],
            'cancelled' => ['draft', 'active'],
        ],
        'task' => [
            'open' => ['todo', 'in_progress', 'review', 'blocked', 'done', 'completed', 'cancelled'],
            'todo' => ['open', 'in_progress', 'review', 'blocked', 'done', 'completed', 'cancelled'],
            'in_progress' => ['open', 'todo', 'review', 'blocked', 'done', 'completed', 'cancelled'],
            'review' => ['in_progress', 'done', 'completed', 'cancelled', 'todo'],
            'blocked' => ['in_progress', 'open', 'todo', 'cancelled'],
            'done' => ['open', 'todo', 'in_progress'],
            'completed' => ['open', 'todo', 'in_progress'],
            'cancelled' => ['open', 'todo'],
        ],
        'ticket' => [
            'open' => ['pending', 'in_progress', 'resolved', 'closed'],
            'pending' => ['open', 'in_progress', 'resolved', 'closed'],
            'in_progress' => ['pending', 'resolved', 'closed', 'open'],
            'resolved' => ['closed', 'open'],
            'closed' => ['open'],
        ],
        'appointment' => [
            'requested' => ['scheduled', 'confirmed', 'cancelled'],
            'pending' => ['scheduled', 'confirmed', 'cancelled'],
            'scheduled' => ['confirmed', 'completed', 'cancelled', 'no_show', 'requested'],
            'confirmed' => ['completed', 'cancelled', 'no_show', 'scheduled'],
            'completed' => [],
            'cancelled' => ['requested', 'scheduled'],
            'no_show' => ['scheduled'],
        ],
    ];

    public static function assert(?string $from, string $to, string $machine, string $field = 'status'): void
    {
        $map = self::TRANSITIONS[$machine] ?? null;
        if ($map === null) {
            throw ValidationException::withMessages([$field => 'Unknown status machine.']);
        }

        $known = array_keys($map);
        if (! in_array($to, $known, true)) {
            throw ValidationException::withMessages([
                $field => 'Invalid status.',
            ]);
        }

        if ($from === null || $from === '' || $from === $to) {
            return;
        }

        $allowed = $map[$from] ?? $known;
        if (! in_array($to, $allowed, true)) {
            throw ValidationException::withMessages([
                $field => "Cannot change status from {$from} to {$to}.",
            ]);
        }
    }
}

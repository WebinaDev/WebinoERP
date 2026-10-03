<?php

namespace Modules\Projects\Services;

use Modules\Projects\Entities\PrjTicket;

class TicketSla
{
    /** @var array<string, int> hours until first response */
    private const HOURS = [
        'urgent' => 4,
        'high' => 8,
        'normal' => 24,
        'low' => 72,
    ];

    public function apply(PrjTicket $ticket): PrjTicket
    {
        $priority = strtolower((string) ($ticket->priority ?: 'normal'));
        $hours = self::HOURS[$priority] ?? self::HOURS['normal'];
        $ticket->sla_first_due_at = now()->addHours($hours);
        $ticket->sla_resolve_due_at = now()->addHours($hours * 4);
        $ticket->save();

        return $ticket;
    }

    public function markResponse(PrjTicket $ticket): void
    {
        if ($ticket->first_responded_at) {
            return;
        }
        $ticket->first_responded_at = now();
        if ($ticket->sla_first_due_at && now()->greaterThan($ticket->sla_first_due_at)) {
            $ticket->sla_breached_at = $ticket->sla_breached_at ?? now();
        }
        $ticket->save();
    }

    public function refreshBreaches(): int
    {
        $count = 0;
        PrjTicket::query()
            ->whereNull('first_responded_at')
            ->whereNull('sla_breached_at')
            ->whereNotNull('sla_first_due_at')
            ->where('sla_first_due_at', '<', now())
            ->whereNotIn('status', ['closed', 'resolved'])
            ->orderBy('id')
            ->each(function (PrjTicket $ticket) use (&$count) {
                $ticket->update(['sla_breached_at' => now()]);
                $count++;
            });

        return $count;
    }
}

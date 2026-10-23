<?php

namespace Modules\Crm\Http\Resources\Hub;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Customer 360 project card (contract §5.3). */
class ProjectResource extends JsonResource
{
    public static $wrap = 'data';

    public const CLOSED = ['completed', 'cancelled'];

    /** @param array{money:bool, manage:bool, tasks:array{total:int,done:int,overdue:int}, spent_seconds:int, billable_amount:float, invoiced_amount:float, manager:?array, contract:?array, members:array} $x */
    public static function fromRow(object $p, array $x): array
    {
        $t = $x['tasks'];
        $due = ! empty($p->due_date) ? CarbonImmutable::parse($p->due_date) : null;
        $progress = isset($p->progress_pct) && $p->progress_pct !== null
            ? (int) $p->progress_pct
            : ($p->status === 'completed' ? 100 : ($t['total'] ? (int) round($t['done'] * 100 / $t['total']) : 0));

        return [
            'id' => (int) $p->id, 'name' => $p->name, 'description' => $p->description ?? null, 'status' => $p->status,
            'manager' => $x['manager'],
            'members' => $x['members'],
            'start_date' => ! empty($p->start_date) ? CarbonImmutable::parse($p->start_date)->toDateString() : null,
            'due_date' => $due?->toDateString(),
            'overdue' => $due && $due->endOfDay()->isPast() && ! in_array($p->status, self::CLOSED, true),
            'budget' => $x['money'] && (isset($p->budget) || isset($p->budget_amount)) ? (float) ($p->budget ?? $p->budget_amount) : null,
            'spent_hours' => round($x['spent_seconds'] / 3600, 1),
            'billable_amount' => $x['money'] ? $x['billable_amount'] : null,
            'invoiced_amount' => $x['money'] ? $x['invoiced_amount'] : null,
            'tasks' => $t,
            'progress_pct' => max(0, min(100, $progress)),
            'contract' => $x['contract'],
            'url' => '/projects/'.$p->id,
            'created_at' => ! empty($p->created_at) ? CarbonImmutable::parse($p->created_at)->toIso8601String() : null,
            'can' => ['update' => $x['manage'], 'add_task' => $x['manage'] && ! in_array($p->status, self::CLOSED, true)],
        ];
    }

    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}

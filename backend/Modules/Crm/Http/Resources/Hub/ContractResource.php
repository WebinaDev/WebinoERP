<?php

namespace Modules\Crm\Http\Resources\Hub;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Customer 360 contract row (contract §5.2). Built by HubContractController from prj_contracts (+ installments) or docs_contracts. */
class ContractResource extends JsonResource
{
    public static $wrap = 'data';

    public static function effectiveStatus(object $c): string
    {
        $s = (string) ($c->status ?? 'draft');
        if (in_array($s, ['active', 'signed'], true) && ! empty($c->end_date) && CarbonImmutable::parse($c->end_date)->endOfDay()->isPast()) {
            return 'expired';
        }

        return $s;
    }

    public static function installmentStatus(object $i): string
    {
        if (! empty($i->paid_at)) {
            return 'paid';
        }

        return ! empty($i->due_date) && CarbonImmutable::parse($i->due_date)->endOfDay()->isPast() ? 'overdue' : 'due';
    }

    /**
     * @param array{money:bool, manage:bool, installments:iterable, projects:array, deal:?array, signed_document:?array, owner:?array, invoiced_total:float} $x
     */
    public static function fromRow(object $c, array $x): array
    {
        $money = $x['money'];
        $status = self::effectiveStatus($c);
        $inst = collect($x['installments'])->map(fn ($i) => [
            'id' => (int) $i->id, 'title' => $i->title ?? null,
            'due_date' => $i->due_date ? CarbonImmutable::parse($i->due_date)->toDateString() : null,
            'amount' => $money ? (float) $i->amount : null,
            'status' => self::installmentStatus($i), 'invoice_id' => isset($i->invoice_id) ? ($i->invoice_id ? (int) $i->invoice_id : null) : null,
        ])->values();
        $paid = (float) collect($x['installments'])->filter(fn ($i) => ! empty($i->paid_at))->sum('amount');
        $end = ! empty($c->end_date) ? CarbonImmutable::parse($c->end_date) : null;
        $m = $x['manage'];

        return [
            'id' => (int) $c->id, 'source' => 'projects',
            'contract_no' => $c->contract_no ?? null, 'title' => $c->title, 'status' => $status,
            'amount' => (float) $c->amount, 'currency' => $c->currency ?? 'IRR', 'tax_pct' => isset($c->tax_pct) ? (float) $c->tax_pct : null,
            'start_date' => ! empty($c->start_date) ? CarbonImmutable::parse($c->start_date)->toDateString() : null,
            'end_date' => $end?->toDateString(),
            'signed_at' => ! empty($c->signed_at) ? CarbonImmutable::parse($c->signed_at)->toIso8601String() : null,
            'renewal_type' => $c->renewal_type ?? 'none', 'renewal_notice_days' => isset($c->renewal_notice_days) ? (int) $c->renewal_notice_days : null,
            'days_to_end' => $end ? (int) CarbonImmutable::today()->diffInDays($end, false) : null,
            'paid_total' => $money ? $paid : null,
            'invoiced_total' => $money ? (float) $x['invoiced_total'] : null,
            'remaining' => $money ? max(0.0, (float) $c->amount - $paid) : null,
            'installments' => $inst,
            'projects' => $x['projects'], 'deal' => $x['deal'], 'signed_document' => $x['signed_document'], 'owner' => $x['owner'],
            'renewed_from_id' => isset($c->renewed_from_id) && $c->renewed_from_id ? (int) $c->renewed_from_id : null,
            'notes' => $c->notes ?? null,
            'created_at' => ! empty($c->created_at) ? CarbonImmutable::parse($c->created_at)->toIso8601String() : null,
            'can' => [
                'update' => $m && $status !== 'cancelled',
                'sign' => $m && in_array($status, ['draft', 'active'], true),
                'cancel' => $m && ! in_array($status, ['cancelled', 'expired'], true),
                'delete' => $m && $status === 'draft',
                'renew' => $m && in_array($status, ['active', 'signed', 'expired'], true),
                'invoice' => $m && $money,
            ],
        ];
    }

    /** docs_contracts row (needs Phase 0 docs_contracts.crm_account_id) — read-only, no money. */
    public static function fromDocs(object $c): array
    {
        return [
            'id' => 'doc-'.$c->id, 'source' => 'docs', 'contract_no' => null, 'title' => $c->title,
            'status' => in_array($c->status, ['draft', 'active', 'signed', 'expired', 'cancelled'], true) ? $c->status : ($c->signed_at ? 'signed' : 'draft'),
            'amount' => null, 'currency' => null, 'tax_pct' => null, 'start_date' => null, 'end_date' => null,
            'signed_at' => $c->signed_at ? CarbonImmutable::parse($c->signed_at)->toIso8601String() : null,
            'renewal_type' => null, 'renewal_notice_days' => null, 'days_to_end' => null,
            'paid_total' => null, 'invoiced_total' => null, 'remaining' => null, 'installments' => [], 'projects' => [],
            'deal' => null, 'signed_document' => null, 'owner' => null, 'notes' => null,
            'body_excerpt' => mb_substr(trim(strip_tags((string) ($c->body ?? ''))), 0, 280),
            'created_at' => $c->created_at ? CarbonImmutable::parse($c->created_at)->toIso8601String() : null,
            'can' => ['update' => false, 'sign' => false, 'cancel' => false, 'delete' => false, 'renew' => false, 'invoice' => false],
        ];
    }

    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}

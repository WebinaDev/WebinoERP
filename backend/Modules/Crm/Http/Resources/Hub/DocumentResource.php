<?php

namespace Modules\Crm\Http\Resources\Hub;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Customer 360 document row (contract §5.1). Wraps either a crm_customer_documents row
 * or a read-only row synthesized from prj_files / docs_files (`prjfile-N`, `docfile-N`).
 *
 * @property array $resource
 */
class DocumentResource extends JsonResource
{
    public static $wrap = 'data';

    /** @param array{can_manage?:bool,refs?:array} $ctx */
    public static function fromRow(object|array $d, array $ctx = []): array
    {
        $d = (object) $d;
        $source = $d->source ?? 'hub';
        $hub = $source === 'hub';
        $json = fn ($v) => is_array($v) ? $v : (is_string($v) && $v !== '' ? (json_decode($v, true) ?: []) : []);
        $exp = $d->expires_at ?? null;
        $refs = $ctx['refs'] ?? [];
        $manage = $hub && ($ctx['can_manage'] ?? false);

        return [
            'id' => $hub ? (int) $d->id : $d->id,
            'source' => $source,
            'category' => $d->category ?? 'other',
            'title' => $d->title ?? '',
            'url' => $d->url ?? null,
            'has_file' => ! empty($d->file_path ?? null),
            'file_name' => $d->file_name ?? null,
            'mime' => $d->file_mime ?? $d->mime ?? null,
            'size' => isset($d->file_size) || isset($d->size) ? (int) ($d->file_size ?? $d->size) : null,
            'version' => (int) ($d->version ?? 1),
            'expires_at' => $exp ? CarbonImmutable::parse($exp)->toDateString() : null,
            'expired' => $exp ? CarbonImmutable::parse($exp)->endOfDay()->isPast() : false,
            'review_status' => $d->review_status ?? null,
            'review_note' => $d->review_note ?? null,
            'reviewed_by' => isset($d->reviewed_by) ? ($refs['users'][$d->reviewed_by] ?? ['id' => (int) $d->reviewed_by, 'name' => null]) : null,
            'reviewed_at' => isset($d->reviewed_at) && $d->reviewed_at ? CarbonImmutable::parse($d->reviewed_at)->toIso8601String() : null,
            'visible_to_roles' => $json($d->visible_to_roles ?? null),
            'tags' => $json($d->tags ?? null),
            'contract' => ! empty($d->contract_id) ? ($refs['contracts'][$d->contract_id] ?? ['id' => (int) $d->contract_id, 'title' => null]) : null,
            'project' => ! empty($d->project_id) ? ($refs['projects'][$d->project_id] ?? ['id' => (int) $d->project_id, 'name' => null]) : null,
            'created_by' => ! empty($d->created_by) ? ($refs['users'][$d->created_by] ?? ['id' => (int) $d->created_by, 'name' => null]) : null,
            'created_at' => ! empty($d->created_at) ? CarbonImmutable::parse($d->created_at)->toIso8601String() : null,
            'can' => ['update' => $manage, 'delete' => $manage, 'review' => $manage],
        ];
    }

    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}

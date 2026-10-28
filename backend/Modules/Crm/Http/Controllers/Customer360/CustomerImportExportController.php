<?php

namespace Modules\Crm\Http\Controllers\Customer360;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Crm\Services\Customer360\CustomerImportExportService as Svc;
use Modules\Hrm\Support\SimpleSpreadsheet;

/** PHASE5_API.md §2 — customers / addresses / contacts export, template and import. */
class CustomerImportExportController extends Hub360Base
{
    public function export(Request $r, Svc $svc): Response
    {
        $v = $this->params($r);
        $ids = array_map('intval', (array) $r->query('ids', []));
        $q = is_string($r->query('q')) ? trim($r->query('q')) : null;

        return $this->file($svc->encode($svc->rows($v['entity'], $ids, $q ?: null), $v['format']), Svc::filename($v['entity'], $v['format']), $v['format']);
    }

    public function template(Request $r, Svc $svc): Response
    {
        $v = $this->params($r);

        return $this->file($svc->encode([Svc::COLUMNS[$v['entity']]], $v['format']), 'customers-'.$v['entity'].'-template.'.$v['format'], $v['format']);
    }

    public function import(Request $r, Svc $svc): JsonResponse
    {
        abort_unless($this->access->canManage($r->user(), 'import_export'), 403, __('Forbidden'));
        $v = $r->validate([
            'file' => 'required|file|max:5120|mimes:csv,txt,xlsx', 'entity' => 'required|in:customers,addresses,contacts',
            'mode' => 'nullable|in:create,upsert', 'dry_run' => 'nullable|boolean',
        ]);
        $file = $r->file('file');
        $sheet = SimpleSpreadsheet::read($file->getRealPath(), $file->getClientOriginalName());
        $res = $svc->import($v['entity'], $sheet, $v['mode'] ?? 'upsert', (bool) ($v['dry_run'] ?? false), $r->user()->id);

        return response()->json(['data' => $res]);
    }

    /** @return array{entity:string,format:string} */
    private function params(Request $r): array
    {
        abort_unless($this->access->canManage($r->user(), 'import_export'), 403, __('Forbidden'));
        $v = $r->validate(['entity' => 'nullable|in:customers,addresses,contacts', 'format' => 'nullable|in:csv,xlsx', 'ids' => 'nullable|array', 'ids.*' => 'integer']);

        return ['entity' => $v['entity'] ?? 'customers', 'format' => $v['format'] ?? 'csv'];
    }

    private function file(string $body, string $name, string $format): Response
    {
        return response($body, 200, [
            'Content-Type' => $format === 'xlsx' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$name.'"', 'Cache-Control' => 'private, no-store',
        ]);
    }
}

<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Entities\OpsAuditLog;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ComplianceController extends Controller
{
    public function audit(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->rows($request)]);
    }

    public function auditCsv(Request $request): StreamedResponse
    {
        $rows = $this->rows($request);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'module', 'action', 'subject_type', 'subject_id', 'user_id', 'sandbox', 'created_at']);
            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['id'],
                    $row['module'],
                    $row['action'],
                    $row['subject_type'],
                    $row['subject_id'],
                    $row['user_id'],
                    $row['sandbox'] ? '1' : '0',
                    $row['created_at'],
                ]);
            }
            fclose($out);
        }, 'compliance-audit.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(Request $request): array
    {
        $query = OpsAuditLog::query()->orderBy('id');
        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->date('from')->startOfDay());
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->date('to')->endOfDay());
        }
        if ($request->filled('module')) {
            $query->where('module', (string) $request->query('module'));
        }
        if ($request->has('sandbox')) {
            $query->where('sandbox', $request->boolean('sandbox'));
        }

        return $query->limit(5000)->get()->map(fn (OpsAuditLog $row) => [
            'id' => $row->id,
            'module' => $row->module,
            'action' => $row->action,
            'subject_type' => $row->subject_type,
            'subject_id' => $row->subject_id,
            'user_id' => $row->user_id,
            'sandbox' => (bool) $row->sandbox,
            'changes' => $row->changes,
            'created_at' => optional($row->created_at)?->toIso8601String(),
        ])->all();
    }
}

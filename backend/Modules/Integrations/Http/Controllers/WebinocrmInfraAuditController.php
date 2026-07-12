<?php

namespace Modules\Integrations\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Entities\CoreInfraAuditLog;

class WebinocrmInfraAuditController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = min(max((int) $request->query('limit', 50), 1), 200);

        $rows = CoreInfraAuditLog::query()
            ->with('user:id,name,email')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'user_id', 'channel', 'action', 'subject_type', 'subject_id', 'payload', 'created_at']);

        return response()->json(['data' => $rows]);
    }
}

<?php

namespace Modules\Core\Http\Controllers;

use App\Support\AppliesIndexQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Entities\CoreSystemLog;

class SystemLogController extends Controller
{
    use AppliesIndexQuery;

    public function index(Request $request): JsonResponse
    {
        $query = CoreSystemLog::query();
        $paginator = $this->applyIndexQuery(
            $query,
            $request,
            ['severity' => 'severity', 'level' => 'level', 'channel' => 'channel'],
            ['message', 'action'],
            ['id', 'created_at', 'severity', 'level'],
        );

        return $this->paginatedJsonResponse($paginator);
    }
}

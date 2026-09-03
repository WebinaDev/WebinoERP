<?php

namespace Modules\Platform\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Platform\Entities\PlatformDeployment;
use Modules\Platform\Entities\PlatformProject;
use Modules\Platform\Entities\PlatformResource;
use Modules\Platform\Entities\PlatformServer;

class DashboardController extends Controller
{
    public function summary(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'servers' => PlatformServer::query()->count(),
                'projects' => PlatformProject::query()->count(),
                'resources' => PlatformResource::query()->count(),
                'running' => PlatformResource::query()->where('status', 'running')->count(),
                'recent_deployments' => PlatformDeployment::query()->latest()->limit(10)->get(),
                'servers_list' => PlatformServer::query()->latest()->limit(8)->get(),
                'projects_list' => PlatformProject::query()->latest()->limit(8)->get(),
            ],
            'message' => null,
            'meta' => null,
            'errors' => null,
        ]);
    }
}

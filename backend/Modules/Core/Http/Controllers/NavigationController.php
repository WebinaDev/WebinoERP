<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Services\DashboardNavigationService;

class NavigationController extends Controller
{
    public function __construct(
        private readonly DashboardNavigationService $navigation
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Not authenticated'],
            ], 401);
        }

        $dashboardRole = $this->navigation->resolveDashboardRole($user);

        return response()->json([
            'data' => [
                'dashboard_role' => $dashboardRole,
                'items' => $this->navigation->menuForUser($user),
            ],
        ]);
    }
}

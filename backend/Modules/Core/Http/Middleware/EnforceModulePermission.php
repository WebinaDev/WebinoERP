<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves required Spatie permission from config/module_permissions.php
 * using module slug + first URI segment + HTTP method (view vs manage).
 */
class EnforceModulePermission
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Authentication required.'],
            ], 401);
        }

        if ($user->hasRole('system_manager')) {
            return $next($request);
        }

        $permission = $this->resolvePermission($module, $request);

        if ($permission && ! $user->can($permission)) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You do not have permission to perform this action.',
                    'permission' => $permission,
                ],
            ], 403);
        }

        return $next($request);
    }

    private function resolvePermission(string $module, Request $request): ?string
    {
        $map = config("module_permissions.{$module}", []);
        if ($map === []) {
            return null;
        }

        $path = trim($request->path(), '/');
        $prefix = "api/v1/{$module}/";
        $relative = str_starts_with($path, $prefix)
            ? substr($path, strlen($prefix))
            : $path;

        $segment = explode('/', $relative)[0] ?: '*';
        $rules = $map[$segment] ?? $map['*'] ?? null;

        if (! $rules) {
            return null;
        }

        $isRead = in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true);

        return $isRead ? ($rules['view'] ?? null) : ($rules['manage'] ?? $rules['view'] ?? null);
    }
}

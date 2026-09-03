<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves required Spatie permission from config/module_permissions.php
 * using module slug + first URI segment + HTTP method (view vs manage).
 *
 * Fail-closed: missing map / missing segment rules ⇒ 403 (not allow).
 *
 * Optional second parameter is the URI prefix slug when it differs from the
 * config key, e.g. module.permission:site_builder,site-builder
 */
class EnforceModulePermission
{
    public function handle(Request $request, Closure $next, string $module, ?string $uriSlug = null): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => __('api.authentication_required'),
                'errors' => ['code' => 'UNAUTHORIZED'],
            ], 401);
        }

        if ($user->hasRole('system_manager')) {
            return $next($request);
        }

        $permission = $this->resolvePermission($module, $request, $uriSlug);

        if ($permission === null) {
            if (app()->environment('local')) {
                Log::warning('module.permission.unmapped', [
                    'module' => $module,
                    'path' => $request->path(),
                    'method' => $request->method(),
                ]);
            }

            return response()->json([
                'message' => __('api.permission_denied'),
                'errors' => [
                    'code' => 'FORBIDDEN',
                    'permission' => null,
                    'reason' => 'unmapped',
                ],
            ], 403);
        }

        if (! $user->can($permission)) {
            return response()->json([
                'message' => __('api.permission_denied'),
                'errors' => [
                    'code' => 'FORBIDDEN',
                    'permission' => $permission,
                ],
            ], 403);
        }

        return $next($request);
    }

    private function resolvePermission(string $module, Request $request, ?string $uriSlug): ?string
    {
        $map = config("module_permissions.{$module}", []);
        if ($map === []) {
            return null;
        }

        $path = trim($request->path(), '/');
        $slug = $uriSlug ?: str_replace('_', '-', $module);
        $prefix = "api/v1/{$slug}/";

        // Prefer the route group's real prefix when available.
        $routePrefix = trim((string) ($request->route()?->getPrefix() ?? ''), '/');
        if ($routePrefix !== '' && str_starts_with($path, $routePrefix.'/')) {
            $relative = substr($path, strlen($routePrefix) + 1);
        } elseif (str_starts_with($path, $prefix)) {
            $relative = substr($path, strlen($prefix));
        } elseif ($path === rtrim($prefix, '/')) {
            $relative = '';
        } else {
            $relative = $path;
        }

        $segment = explode('/', $relative)[0] ?: '*';
        $rules = $map[$segment] ?? $map['*'] ?? null;

        if (! is_array($rules)) {
            return null;
        }

        $isRead = in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true);

        $permission = $isRead ? ($rules['view'] ?? null) : ($rules['manage'] ?? $rules['view'] ?? null);

        return is_string($permission) && $permission !== '' ? $permission : null;
    }
}

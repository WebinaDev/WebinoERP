<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Modules\Core\Entities\SystemModule;

class CheckModuleLicense
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $moduleSlug): Response
    {
        $module = SystemModule::where('slug', $moduleSlug)->first();

        if (!$module || !$module->isLicensed()) {
            return response()->json([
                'error' => [
                    'code' => 'MODULE_NOT_ACTIVE',
                    'message' => 'This feature is not enabled. Please activate the module license.',
                ],
            ], 403);
        }

        return $next($request);
    }
}


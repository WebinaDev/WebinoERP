<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UpdateTokenLastActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $token = $request->user()?->currentAccessToken();
        if ($token) {
            $token->forceFill([
                'last_activity_at' => now(),
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 2000),
            ])->save();
        }

        return $response;
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiResponseFormatter
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // No body (e.g. 204) — never wrap or json_decode
        if ($response->isEmpty() || $response->getStatusCode() === 204) {
            return $response;
        }

        $raw = $response->getContent();

        // Only format JSON responses
        if ($response->headers->get('Content-Type') === 'application/json' ||
            $request->wantsJson()) {

            $content = json_decode($raw, true);

            // Invalid or non-array JSON (e.g. empty string)
            if (! is_array($content)) {
                return $response;
            }

            // If response is already formatted, return as is
            if (isset($content['data']) || isset($content['error'])) {
                return $response;
            }

            // Format success response
            if ($response->isSuccessful()) {
                $formatted = [
                    'data' => $content,
                ];

                if (isset($content['message'])) {
                    $formatted['message'] = $content['message'];
                    unset($formatted['data']['message']);
                }

                $response->setContent(json_encode($formatted));
            }
        }

        return $response;
    }
}


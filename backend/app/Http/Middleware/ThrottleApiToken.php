<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottleApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        // Public site API (guests): limited per visitor by throttle:marketing-public; the site's server-side
        // renders all come from one container IP, so this shared guest bucket turned busy minutes into 404 pages.
        if ($user === null && $request->is('api/v1/public/*')) {
            return $next($request);
        }
        $wizard = $this->isSiteBuilderWizardRequest($request);
        $limit = $this->maxAttempts($wizard);

        $token = $user?->currentAccessToken();
        // Wizard launch/status/prepare-license polling must not consume the
        // shared API token bucket (default 120/min) or auth stays protected
        // on its own limiters while a provision is in progress.
        $key = $wizard
            ? 'site-builder-wizard:'.($user?->id ?? 'guest').'|'.$request->ip()
            : ($token !== null
                ? 'api-token:'.$token->id
                : 'api-user:'.($user?->id ?? 'guest').'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            $retryAfter = RateLimiter::availableIn($key);

            return response()->json([
                'message' => __('tokens.rate_limited'),
            ], 429)->withHeaders([
                'Retry-After' => (string) $retryAfter,
                'X-RateLimit-Limit' => (string) $limit,
                'X-RateLimit-Remaining' => '0',
            ]);
        }

        RateLimiter::hit($key, 60);

        $response = $next($request);
        $remaining = max(0, $limit - RateLimiter::attempts($key));

        if ($response instanceof Response) {
            $response->headers->set('X-RateLimit-Limit', (string) $limit);
            $response->headers->set('X-RateLimit-Remaining', (string) $remaining);
        }

        return $response;
    }

    private function maxAttempts(bool $wizard): int
    {
        if ($wizard) {
            return max(1, (int) config('sitebuilder.wizard_rate_limit_per_minute', 600));
        }

        $configured = config('api.rate_limit_per_minute');
        if ($configured !== null && $configured !== '') {
            return max(1, (int) $configured);
        }

        return max(1, (int) config('services.api_rate_limit_per_minute', 120));
    }

    private function isSiteBuilderWizardRequest(Request $request): bool
    {
        return $request->is(
            'api/v1/site-builder/provisions/*/launch',
            'api/v1/site-builder/provisions/*/status',
            'api/v1/site-builder/provisions/*/prepare-license',
            'api/v1/site-builder/provisions/*/retry',
            'api/v1/site-builder/provisions/*/logs',
        );
    }
}

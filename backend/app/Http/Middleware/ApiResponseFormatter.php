<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiResponseFormatter
{
    /**
     * Normalize API responses to the Webina envelope:
     * { success, data, message, meta, errors }
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (str_ends_with($request->path(), 'openapi.json')) {
            return $next($request);
        }

        $response = $next($request);

        if ($response->isEmpty() || $response->getStatusCode() === 204) {
            return $response;
        }

        if (! $this->shouldFormat($request, $response)) {
            return $response;
        }

        $raw = $response->getContent();
        $content = json_decode($raw, true);

        if (! is_array($content)) {
            return $response;
        }

        if ($this->isEnvelope($content)) {
            return $response;
        }

        $status = $response->getStatusCode();
        $success = $status >= 200 && $status < 300;

        if ($success) {
            $formatted = $this->formatSuccess($content);
        } else {
            $formatted = $this->formatError($content, $status);
        }

        return response()->json($formatted, $status, $response->headers->all());
    }

    private function shouldFormat(Request $request, Response $response): bool
    {
        if ($response->headers->get('Content-Type') === 'application/json') {
            return true;
        }

        return $request->wantsJson() || str_starts_with($request->path(), 'api/');
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function isEnvelope(array $content): bool
    {
        return array_key_exists('success', $content)
            && (array_key_exists('data', $content) || array_key_exists('errors', $content));
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function formatSuccess(array $content): array
    {
        $message = null;
        $meta = null;
        $data = $content;

        if (isset($content['message']) && is_string($content['message'])) {
            $message = $content['message'];
            unset($data['message']);
        }

        if (isset($content['meta']) && is_array($content['meta'])) {
            $meta = $content['meta'];
            unset($data['meta']);
        }

        if (isset($content['data'])) {
            $data = $content['data'];
        }

        if (count($data) === 1 && array_key_exists('data', $data) && is_array($data['data'])) {
            $data = $data['data'];
        }

        return [
            'success' => true,
            'data' => $data,
            'message' => $message,
            'meta' => $meta,
            'errors' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function formatError(array $content, int $status): array
    {
        $message = null;
        $errors = null;
        $data = null;

        if (isset($content['error']) && is_array($content['error'])) {
            $error = $content['error'];
            $message = is_string($error['message'] ?? null) ? $error['message'] : null;
            $errors = $error;
        } elseif (isset($content['message']) && is_string($content['message'])) {
            $message = $content['message'];
        }

        if (isset($content['errors']) && is_array($content['errors'])) {
            $errors = $content['errors'];
        }

        if ($message === null) {
            $message = match ($status) {
                401 => 'auth.unauthorized',
                403 => 'auth.forbidden',
                404 => 'errors.not_found',
                422 => 'validation.failed',
                default => 'errors.server',
            };
        }

        return [
            'success' => false,
            'data' => $data,
            'message' => $message,
            'meta' => null,
            'errors' => $errors,
        ];
    }
}

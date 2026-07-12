<?php

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Services\FieldSecurityService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that masks / removes fields in a JSON response based on `FieldPermission` rules.
 *
 * Apply in routes with `->middleware('fieldsec:<entity_type>')` where entity is e.g. `lead`, `contract`.
 *
 * The middleware walks the JSON structure recursively and applies filtering to any object that
 * *looks like* the target entity (has at least one field from the rule set). This is defensive
 * and does not require resources to know about field security.
 */
class ApplyFieldPermissions
{
    public function __construct(private FieldSecurityService $service) {}

    public function handle(Request $request, Closure $next, string $entity = ''): Response
    {
        $response = $next($request);
        if (! $response instanceof JsonResponse || $entity === '') {
            return $response;
        }
        $user = $request->user();
        if ($user === null) {
            return $response;
        }
        $payload = $response->getData(true);
        if (! is_array($payload)) {
            return $response;
        }

        $response->setData($this->walk($payload, $entity, $user));

        return $response;
    }

    private function walk(array $data, string $entity, $user): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (array_is_list($value)) {
                    foreach ($value as $i => $row) {
                        if (is_array($row)) {
                            $value[$i] = $this->walk($row, $entity, $user);
                        }
                    }
                    $data[$key] = $value;

                    continue;
                }
                $data[$key] = $this->service->applyToArray($user, $entity, $this->walk($value, $entity, $user));
            }
        }

        return $data;
    }
}

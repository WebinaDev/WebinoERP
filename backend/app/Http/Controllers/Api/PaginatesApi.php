<?php

namespace App\Http\Controllers\Api;

use App\Support\AppliesIndexQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

trait PaginatesApi
{
    use AppliesIndexQuery;
    protected function paginatedResponse(LengthAwarePaginator $paginator): JsonResponse
    {
        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ]);
    }

    protected function perPage(Request $request, int $default = 15, int $max = 100): int
    {
        return min((int) $request->input('per_page', $default), $max);
    }
}

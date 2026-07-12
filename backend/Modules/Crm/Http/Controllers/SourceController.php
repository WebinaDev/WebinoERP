<?php

namespace Modules\Crm\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Crm\Entities\CrmSource;

class SourceController extends Controller
{
    use PaginatesApi;

    public function index(Request $request): JsonResponse
    {
        return $this->paginatedResponse(CrmSource::query()->orderBy('sort_order')->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:7',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);
        $source = CrmSource::create($data);

        return response()->json(['data' => $source, 'message' => 'Source created'], 201);
    }
}

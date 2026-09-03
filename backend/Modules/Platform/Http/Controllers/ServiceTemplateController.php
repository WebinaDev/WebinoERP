<?php

namespace Modules\Platform\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Platform\Entities\PlatformServiceTemplate;

class ServiceTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = PlatformServiceTemplate::query()->orderBy('category')->orderBy('name');
        if ($request->filled('category')) {
            $q->where('category', $request->string('category'));
        }
        return response()->json(['success' => true, 'data' => $q->get(), 'message' => null, 'meta' => null, 'errors' => null]);
    }

    public function show(string $slug): JsonResponse
    {
        $row = PlatformServiceTemplate::query()->where('slug', $slug)->firstOrFail();
        return response()->json(['success' => true, 'data' => $row, 'message' => null, 'meta' => null, 'errors' => null]);
    }
}

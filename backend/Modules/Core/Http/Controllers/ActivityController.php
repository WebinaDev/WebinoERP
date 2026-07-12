<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Entities\CoreActivity;

class ActivityController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 30), 100);
        $q = CoreActivity::query()->with('user:id,name,email')->orderByDesc('id');

        if ($request->filled('subject_type')) {
            $q->where('subject_type', $request->string('subject_type')->toString());
        }
        if ($request->filled('subject_id')) {
            $q->where('subject_id', (int) $request->input('subject_id'));
        }
        if ($request->filled('action')) {
            $q->where('action', $request->string('action')->toString());
        }
        if ($request->filled('since')) {
            $q->where('created_at', '>=', $request->string('since')->toString());
        }

        return response()->json(['data' => $q->paginate($perPage)]);
    }
}

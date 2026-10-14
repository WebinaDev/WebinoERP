<?php

namespace Modules\AiContent\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AiContent\Entities\AiJob;
use Modules\AiContent\Services\ContentAssist;

/** POST /api/v1/ai-content/content-assist — suggestions only; the editor decides whether to apply them. */
class ContentAssistController extends Controller
{
    public function assist(Request $request, ContentAssist $assist): JsonResponse
    {
        $data = $request->validate([
            'action' => 'required|in:'.implode(',', array_keys(ContentAssist::ACTIONS)),
            'surface' => 'required|string|max:64|regex:/^[\w.\-]+$/',
            'field' => 'nullable|string|max:64',
            'text' => 'nullable|string|max:40000',
            'format' => 'nullable|in:text,html',
            'tone' => 'nullable|in:'.implode(',', array_keys(ContentAssist::TONES)),
            'target_locale' => 'nullable|in:fa,en',
            'target_id' => 'nullable|integer',
            'context' => 'nullable|array',
        ]);
        $data += ['field' => null];

        return response()->json(['data' => $assist->run($data)]);
    }

    public function history(Request $request): JsonResponse
    {
        $rows = AiJob::query()->where('job_type', 'content_assist')
            ->when($request->filled('surface'), fn ($q) => $q->where('target_type', 'like', $request->get('surface').'%'))
            ->orderByDesc('id')->limit(100)->get(['id', 'target_type', 'target_id', 'status', 'provider', 'model', 'tokens_in', 'tokens_out', 'cost_toman', 'result_summary', 'payload', 'created_at']);

        return response()->json(['data' => $rows]);
    }
}

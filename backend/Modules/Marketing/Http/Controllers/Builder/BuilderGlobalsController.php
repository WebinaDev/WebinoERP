<?php

namespace Modules\Marketing\Http\Controllers\Builder;

use Modules\Marketing\Http\Controllers\Controller;
use Modules\Marketing\Entities\BuilderGlobal;
use Modules\Marketing\Services\Builder\BuilderGlobalsNormalizer;
use Modules\Marketing\Services\Builder\BuilderSite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuilderGlobalsController extends Controller
{
        public function show(Request $request): JsonResponse
    {
        $row = BuilderGlobal::query()->where('tenant_id', BuilderSite::ID)->first();
        $normalizer = app(BuilderGlobalsNormalizer::class);

        return response()->json(['data' => [
            'has_draft' => is_array($row?->draft),
            'has_published' => is_array($row?->published),
            'settings' => $normalizer->normalize(is_array($row?->draft) ? $row->draft : (is_array($row?->published) ? $row->published : [])),
        ]]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate(['settings' => 'required|array']);
        $settings = app(BuilderGlobalsNormalizer::class)->normalize($data['settings']);
        $json = json_encode($settings);
        abort_if($json === false || strlen($json) > 200000, 422, 'settings too large');
        $row = BuilderGlobal::query()->updateOrCreate(
            ['tenant_id' => BuilderSite::ID],
            ['draft' => $settings],
        );

        return response()->json(['data' => [
            'has_draft' => true,
            'has_published' => is_array($row->published),
            'settings' => $settings,
        ]]);
    }

    public function publish(Request $request): JsonResponse
    {
        $row = BuilderGlobal::query()->where('tenant_id', BuilderSite::ID)->firstOrFail();
        abort_if(! is_array($row->draft), 422, 'draft missing');
        $row->published = $row->draft;
        $row->save();

        return response()->json(['data' => [
            'has_draft' => true,
            'has_published' => true,
            'settings' => $row->published,
        ]]);
    }

    public function publicShow(Request $request): JsonResponse
    {
        $row = BuilderGlobal::query()->where('tenant_id', BuilderSite::ID)->first();
        abort_unless($row && is_array($row->published), 404);

        return response()->json(['data' => [
            'settings' => $row->published,
        ]]);
    }
}

<?php

namespace Modules\Marketing\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Marketing\Entities\MarketingMedia;
use Modules\Marketing\Entities\MarketingMediaFolder;
use Modules\Marketing\Entities\MarketingPage;
use Modules\Marketing\Entities\MarketingSiteSetting;
use Modules\Marketing\Http\Controllers\Concerns\HandlesMarketingCrud;

class MarketingMediaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = MarketingMedia::query()->orderByDesc('id');
        if ($request->filled('folder_id')) {
            $query->where('folder_id', $request->get('folder_id'));
        }
        $paginator = $query->paginate(min((int) $request->get('per_page', 30), 100));

        return response()->json([
            'data' => $paginator->items(),
            'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(), 'total' => $paginator->total()],
        ]);
    }

    public function folders(): JsonResponse
    {
        $folders = MarketingMediaFolder::query()->with('children')->whereNull('parent_id')->get();

        return response()->json(['data' => $folders]);
    }

    public function storeFolder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:marketing_media_folders,id',
        ]);
        $folder = MarketingMediaFolder::query()->create($data);

        return response()->json(['data' => $folder], 201);
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240',
            'folder_id' => 'nullable|exists:marketing_media_folders,id',
            'alt' => 'nullable|string|max:255',
        ]);

        $file = $request->file('file');
        $path = $file->store('marketing', 'public');
        $publicUrl = Storage::disk('public')->url($path);

        $media = MarketingMedia::query()->create([
            'folder_id' => $request->get('folder_id'),
            'path' => $path,
            'mime' => $file->getMimeType(),
            'alt' => $request->get('alt'),
            'public_url' => $publicUrl,
        ]);

        return response()->json(['data' => $media], 201);
    }

    public function destroy(int $id): JsonResponse
    {
        $media = MarketingMedia::query()->findOrFail($id);
        if ($media->path) {
            Storage::disk('public')->delete($media->path);
        }
        $media->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }
}

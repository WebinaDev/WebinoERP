<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Core\Entities\CoreDocument;
use Modules\Core\Entities\CoreDocumentVersion;

class DocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $folderId = $request->input('folder_id');
        $docs = CoreDocument::query()
            ->when($folderId !== null, fn ($q) => $q->where('folder_id', $folderId))
            ->orderByDesc('id')
            ->paginate(min((int) $request->input('per_page', 30), 100));

        return response()->json(['data' => $docs]);
    }

    public function upload(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => 'required|file|max:51200',
            'folder_id' => 'nullable|integer|exists:core_document_folders,id',
        ]);
        $f = $request->file('file');
        $path = $f->store('documents/'.date('Y/m'), 'public');
        $doc = CoreDocument::query()->create([
            'folder_id' => $data['folder_id'] ?? null,
            'owner_id' => $request->user()?->id,
            'disk' => 'public',
            'path' => $path,
            'name' => $f->getClientOriginalName(),
            'mime_type' => $f->getClientMimeType(),
            'size_bytes' => (int) $f->getSize(),
            'last_uploaded_at' => now(),
        ]);
        CoreDocumentVersion::query()->create([
            'document_id' => $doc->id,
            'version_no' => 1,
            'disk' => 'public',
            'path' => $path,
            'size_bytes' => (int) $f->getSize(),
            'uploaded_by' => $request->user()?->id,
        ]);

        return response()->json(['data' => $doc], 201);
    }

    public function destroy(int $id): JsonResponse
    {
        $doc = CoreDocument::query()->findOrFail($id);
        $doc->delete();
        return response()->json([], 204);
    }

    public function download(int $id): JsonResponse
    {
        $doc = CoreDocument::query()->findOrFail($id);
        if (! Storage::disk($doc->disk)->exists($doc->path)) {
            return response()->json(['data' => ['url' => null, 'message' => 'File not found']], 404);
        }

        return response()->json([
            'data' => [
                'url' => '/storage/'.$doc->path,
                'path' => $doc->path,
                'name' => $doc->name,
            ],
        ]);
    }

    public function createFolder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'parent_id' => 'nullable|integer|exists:core_document_folders,id',
        ]);
        $id = DB::table('core_document_folders')->insertGetId([
            'name' => $data['name'],
            'parent_id' => $data['parent_id'] ?? null,
            'owner_id' => $request->user()?->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['data' => DB::table('core_document_folders')->where('id', $id)->first()], 201);
    }

    public function rename(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['name' => 'required|string|max:255']);
        $doc = CoreDocument::query()->findOrFail($id);
        $doc->update(['name' => $data['name']]);
        return response()->json(['data' => $doc->fresh()]);
    }

    public function move(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['folder_id' => 'nullable|integer|exists:core_document_folders,id']);
        $doc = CoreDocument::query()->findOrFail($id);
        $doc->update(['folder_id' => $data['folder_id'] ?? null]);
        return response()->json(['data' => $doc->fresh()]);
    }

    public function share(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'shared_with_user_id' => 'nullable|integer|exists:users,id',
            'expires_in_hours' => 'nullable|integer|min:1|max:720',
        ]);

        CoreDocument::query()->findOrFail($id);
        $token = Str::random(64);
        DB::table('core_document_shares')->insert([
            'document_id' => $id,
            'shared_with_user_id' => $data['shared_with_user_id'] ?? null,
            'token' => $token,
            'expires_at' => now()->addHours((int) ($data['expires_in_hours'] ?? 24)),
            'created_by' => $request->user()?->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['data' => ['token' => $token]]);
    }

    public function versions(int $id): JsonResponse
    {
        CoreDocument::query()->findOrFail($id);
        $rows = CoreDocumentVersion::query()->where('document_id', $id)->orderByDesc('version_no')->get();
        return response()->json(['data' => $rows]);
    }
}

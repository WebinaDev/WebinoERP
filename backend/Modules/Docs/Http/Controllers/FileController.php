<?php

namespace Modules\Docs\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Docs\Entities\DocsFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    use PaginatesApi;

    public function index(Request $request): JsonResponse
    {
        $query = DocsFile::query();
        $paginator = $this->applyIndexQuery(
            $query,
            $request,
            ['folder_id' => 'folder_id', 'mime_type' => 'mime_type'],
            ['name', 'path'],
            ['created_at', 'name', 'size'],
        );

        return $this->paginatedResponse($paginator);
    }

    public function store(Request $request): JsonResponse
    {
        if ($request->hasFile('file')) {
            $uploaded = $request->file('file');
            $path = $uploaded->store('docs/'.now()->format('Y/m'), 'local');
            $file = DocsFile::create([
                'name' => $uploaded->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $uploaded->getClientMimeType(),
                'size' => $uploaded->getSize(),
                'disk' => 'local',
                'uploaded_by' => $request->user()->id,
                'folder_id' => $request->integer('folder_id') ?: null,
            ]);

            return response()->json(['data' => $file, 'message' => 'File uploaded'], 201);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'path' => 'required|string|max:500',
            'mime_type' => 'nullable|string|max:100',
            'size' => 'nullable|integer|min:0',
            'folder_id' => 'nullable|integer',
        ]);
        $data['uploaded_by'] = $request->user()->id;
        $file = DocsFile::create($data);

        return response()->json(['data' => $file, 'message' => 'File registered'], 201);
    }

    public function update(Request $request, DocsFile $file): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'folder_id' => 'nullable|integer',
        ]);
        $file->update($data);

        return response()->json(['data' => $file->fresh(), 'message' => 'File updated']);
    }

    public function download(DocsFile $file): StreamedResponse|JsonResponse
    {
        if (! Storage::disk($file->disk ?? 'local')->exists($file->path)) {
            return response()->json(['message' => 'File not found on disk'], 404);
        }

        return Storage::disk($file->disk ?? 'local')->download($file->path, $file->name);
    }

    public function createFolder(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => 'required|string|max:255', 'parent_id' => 'nullable|integer']);
        $folder = DocsFile::create([
            'name' => $data['name'],
            'path' => 'folder://'.Str::slug($data['name']).'-'.Str::random(6),
            'mime_type' => 'inode/directory',
            'size' => 0,
            'folder_id' => $data['parent_id'] ?? null,
            'uploaded_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $folder, 'message' => 'Folder created'], 201);
    }

    public function share(DocsFile $file): JsonResponse
    {
        $token = Str::random(40);
        $file->update(['share_token' => $token]);

        return response()->json(['data' => ['share_token' => $token, 'file' => $file]]);
    }

    public function versions(DocsFile $file): JsonResponse
    {
        return response()->json(['data' => [['version' => $file->version ?? 1, 'file' => $file]]]);
    }

    public function destroy(DocsFile $file): JsonResponse
    {
        if ($file->mime_type !== 'inode/directory' && Storage::disk($file->disk ?? 'local')->exists($file->path)) {
            Storage::disk($file->disk ?? 'local')->delete($file->path);
        }
        $file->delete();

        return response()->noContent();
    }
}

<?php

namespace Modules\Core\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Core\Entities\CoreUserDocument;
use Symfony\Component\HttpFoundation\Response;

/**
 * Personal documents on the private "local" disk (storage/app/private, never public).
 * Admin routes live under /users/{id}/documents, self-service under /me/documents.
 */
class UserDocumentController extends Controller
{
    private const MAX_KB = 20480;

    private const MIMES = 'pdf,jpg,jpeg,png,webp,heic,doc,docx,xls,xlsx,odt,txt,zip';

    // ---- admin -------------------------------------------------------------

    public function index(int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);

        return response()->json(['data' => $this->list($user)]);
    }

    public function store(Request $request, int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);

        return response()->json(['data' => $this->create($request, $user, true)], 201);
    }

    public function update(Request $request, int $id, int $doc): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        $row = CoreUserDocument::query()->where('user_id', $user->id)->findOrFail($doc);

        return response()->json(['data' => $this->modify($request, $row)]);
    }

    public function download(int $id, int $doc): Response
    {
        $row = CoreUserDocument::query()->where('user_id', $id)->findOrFail($doc);

        return $this->stream($row);
    }

    public function destroy(int $id, int $doc): JsonResponse
    {
        $row = CoreUserDocument::query()->where('user_id', $id)->findOrFail($doc);
        $this->remove($row);

        return response()->json(['data' => ['deleted' => true]]);
    }

    // ---- self-service ------------------------------------------------------

    public function myIndex(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->list($request->user())]);
    }

    public function myStore(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->create($request, $request->user(), false)], 201);
    }

    public function myUpdate(Request $request, int $doc): JsonResponse
    {
        $row = CoreUserDocument::query()->where('user_id', $request->user()->id)->findOrFail($doc);
        abort_if($row->status === 'verified', 403, 'مدرک تأییدشده را فقط مدیر می‌تواند تغییر دهد.');

        return response()->json(['data' => $this->modify($request, $row, false)]);
    }

    public function myDownload(Request $request, int $doc): Response
    {
        $row = CoreUserDocument::query()->where('user_id', $request->user()->id)->findOrFail($doc);

        return $this->stream($row);
    }

    public function myDestroy(Request $request, int $doc): JsonResponse
    {
        $row = CoreUserDocument::query()->where('user_id', $request->user()->id)->findOrFail($doc);
        abort_unless((int) $row->uploaded_by === (int) $request->user()->id && $row->status !== 'verified', 403, 'این مدرک را فقط مدیر می‌تواند حذف کند.');
        $this->remove($row);

        return response()->json(['data' => ['deleted' => true]]);
    }

    // ---- internals ---------------------------------------------------------

    /** @return list<array<string, mixed>> */
    private function list(User $user): array
    {
        return CoreUserDocument::query()->with('uploader:id,name')
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (CoreUserDocument $d) => $d->toArray())
            ->all();
    }

    /** @return array<string, mixed> */
    private function rules(bool $fileRequired, bool $admin = true): array
    {
        $rules = [
            'file' => [$fileRequired ? 'required' : 'nullable', 'file', 'max:'.self::MAX_KB, 'mimes:'.self::MIMES],
            'type' => [$fileRequired ? 'required' : 'sometimes', Rule::in(CoreUserDocument::TYPES)],
            'title' => [$fileRequired ? 'nullable' : 'sometimes', 'nullable', 'string', 'max:191'],
            'number' => 'sometimes|nullable|string|max:100',
            'issued_at' => 'sometimes|nullable|date',
            'expires_at' => 'sometimes|nullable|date',
            'notes' => 'sometimes|nullable|string|max:5000',
        ];
        if ($admin) {
            $rules['status'] = 'sometimes|in:valid,pending,verified,rejected';
        }

        return $rules;
    }

    /** @return array<string, mixed> */
    private function create(Request $request, User $user, bool $admin): array
    {
        $data = $request->validate($this->rules(true, $admin));
        $file = $request->file('file');
        $stored = $this->storeFile($user, $file);
        $row = CoreUserDocument::query()->create(array_merge($stored, [
            'user_id' => $user->id,
            'type' => $data['type'],
            'title' => $data['title'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'number' => $data['number'] ?? null,
            'issued_at' => $data['issued_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => $admin ? ($data['status'] ?? 'valid') : 'pending',
            'uploaded_by' => $request->user()->id,
            'version' => 1,
        ]));
        $this->audit('user.document_uploaded', $user, ['document_id' => $row->id, 'type' => $row->type]);

        return $row->fresh('uploader:id,name')->toArray();
    }

    /** @return array<string, mixed> */
    private function modify(Request $request, CoreUserDocument $row, bool $admin = true): array
    {
        $data = $request->validate($this->rules(false, $admin));
        $file = $request->file('file');
        unset($data['file']);
        if ($file) {
            $old = [$row->disk, $row->path];
            $data = array_merge($data, $this->storeFile($row->user, $file), [
                'version' => $row->version + 1,
                'uploaded_by' => $request->user()->id,
            ]);
            if (! $admin) {
                $data['status'] = 'pending';
            }
        }
        $row->fill($data)->save();
        if ($file) {
            Storage::disk($old[0])->delete($old[1]);
        }
        $this->audit($file ? 'user.document_replaced' : 'user.document_updated', $row->user, ['document_id' => $row->id]);

        return $row->fresh('uploader:id,name')->toArray();
    }

    private function remove(CoreUserDocument $row): void
    {
        $user = $row->user;
        try {
            Storage::disk($row->disk)->delete($row->path);
        } catch (\Throwable) {
            // File already gone; still drop the record.
        }
        $row->delete();
        if ($user) {
            $this->audit('user.document_deleted', $user, ['document_id' => $row->id, 'type' => $row->type]);
        }
    }

    private function stream(CoreUserDocument $row): Response
    {
        $disk = Storage::disk($row->disk);
        abort_unless($disk->exists($row->path), 404, 'فایل پیدا نشد.');

        return $disk->download($row->path, $row->original_name, [
            'Content-Type' => $row->mime_type ?: 'application/octet-stream',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @return array<string, mixed> */
    private function storeFile(User $user, \Illuminate\Http\UploadedFile $file): array
    {
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $path = $file->storeAs('user-documents/'.$user->id, Str::uuid()->toString().'.'.$ext, 'local');
        abort_unless(is_string($path) && $path !== '', 500, 'ذخیره فایل ناموفق بود.');

        return [
            'disk' => 'local',
            'path' => $path,
            'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => (int) $file->getSize(),
        ];
    }

    /** @param array<string, mixed> $meta */
    private function audit(string $action, User $subject, array $meta = []): void
    {
        try {
            app(\Modules\Core\Services\ActivityLogger::class)->log($action, $subject, auth()->id(), $meta);
        } catch (\Throwable) {
        }
    }
}

<?php

namespace Modules\Marketing\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Private storage for public form attachments (local disk, never web-served).
 * Each upload lives in form-uploads/{token}/ with a meta.json; the form payload keeps only the token.
 */
class FormUploads
{
    public const DIR = 'form-uploads';

    public const MIMES = 'jpg,jpeg,png,webp,pdf,doc,docx,zip';

    public const MAX_KB = 10240;

    public static function store(UploadedFile $file, string $formSlug): string
    {
        $token = Str::random(40);
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $disk = Storage::disk('local');
        $disk->putFileAs(self::DIR.'/'.$token, $file, 'file.'.$ext);
        $disk->put(self::DIR.'/'.$token.'/meta.json', json_encode([
            'form' => $formSlug,
            'name' => mb_substr($file->getClientOriginalName(), 0, 200),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'ext' => $ext,
            'uploaded_at' => now()->toIso8601String(),
        ], JSON_UNESCAPED_UNICODE));

        return $token;
    }

    public static function valid(string $token): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9]{40}$/', $token) && Storage::disk('local')->exists(self::DIR.'/'.$token.'/meta.json');
    }

    /** @return array<string, mixed>|null */
    public static function meta(string $token): ?array
    {
        if (! self::valid($token)) {
            return null;
        }
        $meta = json_decode((string) Storage::disk('local')->get(self::DIR.'/'.$token.'/meta.json'), true);

        return is_array($meta) ? $meta : null;
    }

    public static function originalName(string $token): ?string
    {
        return self::meta($token)['name'] ?? null;
    }

    public static function path(string $token): ?string
    {
        $meta = self::meta($token);

        return $meta ? self::DIR.'/'.$token.'/file.'.$meta['ext'] : null;
    }
}

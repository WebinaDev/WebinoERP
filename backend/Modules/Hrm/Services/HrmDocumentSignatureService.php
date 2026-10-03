<?php

namespace Modules\Hrm\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Modules\Hrm\Entities\HrmPersonnelDocument;

class HrmDocumentSignatureService
{
    public function sign(HrmPersonnelDocument $document, Request $request, bool $allowResign = false): HrmPersonnelDocument
    {
        $data = $request->validate([
            'signer_name' => 'required|string|max:150',
            'signature_image' => 'nullable|string|max:2500000',
        ]);
        if ($document->document_status === 'signed' && ! $allowResign) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(response()->json(['message' => 'Document already signed'], 422));
        }

        $path = $document->signature_path;
        $image = $data['signature_image'] ?? null;
        if (is_string($image) && str_starts_with($image, 'data:image/')) {
            $path = $this->storeImage($document, $image);
        }

        $audit = $document->audit_log ?? [];
        $audit[] = [
            'action' => $document->document_status === 'signed' ? 'resigned' : 'signed',
            'at' => now()->toIso8601String(),
            'user_id' => $request->user()?->id,
            'signer_name' => $data['signer_name'],
            'ip' => $request->ip(),
            'had_image' => $path !== null,
        ];

        $document->fill([
            'signer_name' => $data['signer_name'],
            'signer_user_id' => $request->user()?->id,
            'signed_at' => now(),
            'signature_path' => $path,
            'signature_placeholder' => $path ? null : 'محل امضا',
            'document_status' => 'signed',
            'audit_log' => $audit,
        ])->save();

        return $document->fresh();
    }

    private function storeImage(HrmPersonnelDocument $document, string $dataUrl): ?string
    {
        if (! preg_match('#^data:image/(png|jpeg|jpg|webp);base64,(.+)$#', $dataUrl, $m)) {
            return $document->signature_path;
        }
        $binary = base64_decode($m[2], true);
        if ($binary === false) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(response()->json(['message' => 'Invalid signature image'], 422));
        }
        $ext = $m[1] === 'jpeg' ? 'jpg' : $m[1];
        $path = 'hrm/signatures/'.$document->employee_id.'/doc-'.$document->id.'.'.$ext;
        Storage::disk('local')->put($path, $binary);

        return $path;
    }
}

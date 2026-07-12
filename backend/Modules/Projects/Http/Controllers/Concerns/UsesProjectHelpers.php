<?php

namespace Modules\Projects\Http\Controllers\Concerns;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Modules\Projects\Entities\WorkflowStatus;

trait UsesProjectHelpers
{
    private function defaultWorkflowStatusId(): ?int
    {
        return WorkflowStatus::query()->orderBy('sort_order')->value('id');
    }

    /** Registers a short-lived token resolvable via GET /api/v1/core/files/pdf/{token}. */
    private function registerPdfDownloadToken(string $path, string $disk = 'public'): string
    {
        $token = Str::random(48);
        Cache::put('pdf_download_token:'.$token, ['disk' => $disk, 'path' => $path], now()->addDays(7));

        return $token;
    }
}

<?php

namespace Modules\Marketplace\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Marketplace\Entities\MarketplaceRelease;

class ReleaseController extends Controller
{
    public function publish(MarketplaceRelease $release): JsonResponse
    {
        $release->update(['status' => 'published', 'published_at' => now()]);
        $release->module()->update(['status' => 'published']);

        return response()->json(['data' => $release->fresh(), 'message' => 'Release published']);
    }

    public function destroy(MarketplaceRelease $release): JsonResponse
    {
        $release->delete();

        return response()->noContent();
    }
}

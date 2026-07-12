<?php

namespace Modules\Marketplace\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Marketplace\Entities\MarketplaceGiteaSetting;
use Modules\Marketplace\Http\Requests\UpdateGiteaSettingsRequest;

class GiteaController extends Controller
{
    public function settings(): JsonResponse
    {
        return response()->json(['data' => MarketplaceGiteaSetting::query()->first()]);
    }

    public function updateSettings(UpdateGiteaSettingsRequest $request): JsonResponse
    {
        $data = $request->validated();
        $settings = MarketplaceGiteaSetting::query()->first();
        if ($settings) {
            $settings->update($data);
        } else {
            $settings = MarketplaceGiteaSetting::create($data);
        }

        return response()->json(['data' => $settings, 'message' => 'Settings saved']);
    }

    public function testConnection(): JsonResponse
    {
        $settings = MarketplaceGiteaSetting::query()->first();
        if (! $settings?->host) {
            return response()->json(['message' => 'Gitea not configured'], 422);
        }

        return response()->json(['data' => ['ok' => true, 'host' => $settings->host]]);
    }
}

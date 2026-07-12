<?php

namespace Modules\Marketplace\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Marketplace\Entities\SiteTheme;

class SiteThemeController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = SiteTheme::query()->orderBy('sort_order')->orderBy('slug')->get();

        return response()->json(['data' => $rows]);
    }
}

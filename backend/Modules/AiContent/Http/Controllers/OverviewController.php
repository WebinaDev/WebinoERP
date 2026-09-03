<?php

namespace Modules\AiContent\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\AiContent\Entities\AiCalendarSlot;
use Modules\AiContent\Entities\AiJob;
use Modules\AiContent\Entities\AiProduct;
use Modules\AiContent\Entities\AiSetting;

class OverviewController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $settings = SettingsController::defaultSettings();
        $row = AiSetting::query()->where('key', 'main')->first();
        if ($row && is_array($row->value)) {
            $settings = array_merge($settings, $row->value);
        }

        $sample = AiProduct::query()
            ->where('status', 'incomplete')
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'name', 'missing'])
            ->map(fn (AiProduct $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'missing' => $p->missing ?? [],
            ])
            ->values()
            ->all();

        return response()->json([
            'jobs_pending' => AiJob::query()->where('status', 'pending')->count(),
            'jobs_failed' => AiJob::query()->where('status', 'failed')->count(),
            'jobs_done' => AiJob::query()->where('status', 'done')->count(),
            'jobs_cost_toman' => (float) AiJob::query()->where('status', 'done')->sum('cost_toman'),
            'calendar_upcoming' => AiCalendarSlot::query()
                ->where('slot_date', '>=', now()->toDateString())
                ->whereIn('status', ['planned', 'queued'])
                ->count(),
            'incomplete_products' => AiProduct::query()->where('status', 'incomplete')->count(),
            'sample_incomplete' => $sample,
            'settings' => $settings,
            'module_enabled' => (bool) ($settings['enabled'] ?? true),
        ]);
    }
}

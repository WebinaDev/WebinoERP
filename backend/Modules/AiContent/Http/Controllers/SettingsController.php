<?php

namespace Modules\AiContent\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AiContent\Entities\AiSetting;

class SettingsController extends Controller
{
    public static function defaultSettings(): array
    {
        return [
            'default_provider' => 'gapgpt',
            'fallback_order' => ['gapgpt', 'openai', 'gemini', 'grok'],
            'grok_model' => '',
            'gemini_model' => '',
            'openai_model' => 'gpt-4o-mini',
            'gapgpt_model' => 'gpt-4o-mini',
            'site_name' => '',
            'site_topic' => '',
            'site_description' => '',
            'palette_mode' => 'site',
            'tone' => 'professional',
            'language' => 'fa',
            'seo_sep' => '|',
            'temperature' => 0.7,
            'max_tokens' => 2000,
            'daily_blog_quota' => 5,
            'daily_product_quota' => 20,
            'auto_publish' => false,
            'publish_status' => 'draft',
            'min_blog_words' => 600,
            'min_product_words' => 200,
            'min_term_words' => 80,
            'min_page_words' => 100,
            'require_site_name' => false,
            'web_research' => false,
            'enabled' => true,
            'do_product' => true,
            'do_product_cat' => true,
            'do_product_brand' => true,
            'do_blog' => true,
            'do_blog_cat' => true,
            'do_page' => true,
            'do_coffee' => false,
            'queue_paused' => false,
            'prompt_system' => '',
            'prompt_product' => '',
            'prompt_product_cat' => '',
            'prompt_product_brand' => '',
            'prompt_blog' => '',
            'prompt_blog_cat' => '',
            'prompt_coffee' => '',
            'prompt_catalog' => '',
            'prompt_title' => '',
            'prompt_page' => '',
            'catalog_assign_categories' => true,
            'catalog_assign_brands' => true,
            'catalog_create_terms' => false,
            'catalog_only_missing' => true,
            'title_enabled' => true,
            'title_pattern' => '',
            'usd_to_toman' => 900000,
            'fields' => [],
            'has_grok_key' => false,
            'has_gemini_key' => false,
            'has_openai_key' => false,
            'has_gapgpt_key' => false,
        ];
    }

    public function show(): JsonResponse
    {
        return response()->json($this->load());
    }

    public function store(Request $request): JsonResponse
    {
        $incoming = $request->all();
        unset(
            $incoming['has_grok_key'],
            $incoming['has_gemini_key'],
            $incoming['has_openai_key'],
            $incoming['has_gapgpt_key'],
            $incoming['grok_api_key_masked'],
            $incoming['gemini_api_key_masked'],
            $incoming['openai_api_key_masked'],
            $incoming['gapgpt_api_key_masked'],
        );

        $stored = $this->rawSettings();
        $secrets = [];
        foreach (['grok_api_key', 'gemini_api_key', 'openai_api_key', 'gapgpt_api_key'] as $key) {
            $secrets[$key] = $stored[$key] ?? null;
            if (array_key_exists($key, $incoming) && filled($incoming[$key])) {
                $secrets[$key] = (string) $incoming[$key];
            }
            unset($incoming[$key]);
        }

        $merged = array_merge($stored, $incoming, array_filter($secrets, fn ($v) => filled($v)));

        AiSetting::query()->updateOrCreate(
            ['key' => 'main'],
            ['value' => $merged],
        );

        return response()->json($this->publicSettings($merged));
    }

    public function queueShow(): JsonResponse
    {
        $settings = $this->load();

        return response()->json(['paused' => (bool) ($settings['queue_paused'] ?? false)]);
    }

    public function queueStore(Request $request): JsonResponse
    {
        $data = $request->validate(['paused' => 'required|boolean']);
        $settings = $this->load();
        $settings['queue_paused'] = (bool) $data['paused'];
        AiSetting::query()->updateOrCreate(['key' => 'main'], ['value' => $settings]);

        return response()->json(['ok' => true, 'paused' => $settings['queue_paused']]);
    }

    public function costEstimate(Request $request): JsonResponse
    {
        $settings = array_merge($this->load(), $request->all());
        $usd = (float) ($settings['usd_to_toman'] ?? 900000);
        $mid = (int) round(0.002 * $usd);

        return response()->json([
            'currency' => 'IRT',
            'usd_to_toman' => $usd,
            'pricing_url' => 'https://gapgpt.app',
            'rates_updated_at' => now()->toIso8601String(),
            'current' => [
                'provider' => $settings['default_provider'] ?? 'gapgpt',
                'model' => $settings['gapgpt_model'] ?? 'gpt-4o-mini',
                'in_per_1m' => 0.15 * $usd,
                'out_per_1m' => 0.6 * $usd,
                'source' => 'stub',
            ],
            'entities' => [
                'product' => [
                    'source' => 'stub',
                    'tokens_in' => ['lo' => 800, 'mid' => 1200, 'hi' => 2000],
                    'tokens_out' => ['lo' => 400, 'mid' => 800, 'hi' => 1500],
                    'cost_toman' => ['lo' => (int) ($mid * 0.5), 'mid' => $mid, 'hi' => $mid * 2],
                ],
                'blog' => [
                    'source' => 'stub',
                    'tokens_in' => ['lo' => 1000, 'mid' => 2000, 'hi' => 4000],
                    'tokens_out' => ['lo' => 800, 'mid' => 1600, 'hi' => 3000],
                    'cost_toman' => ['lo' => $mid, 'mid' => $mid * 2, 'hi' => $mid * 4],
                ],
                'term' => [
                    'source' => 'stub',
                    'tokens_in' => ['lo' => 200, 'mid' => 400, 'hi' => 800],
                    'tokens_out' => ['lo' => 100, 'mid' => 250, 'hi' => 500],
                    'cost_toman' => ['lo' => (int) ($mid * 0.2), 'mid' => (int) ($mid * 0.4), 'hi' => (int) ($mid * 0.8)],
                ],
            ],
            'models' => [],
            'batch' => [],
        ]);
    }

    public function gapGptModels(): JsonResponse
    {
        return response()->json([
            'models' => [
                ['id' => 'gpt-4o-mini', 'owned_by' => 'openai', 'in_per_1m' => 0, 'out_per_1m' => 0],
                ['id' => 'gpt-4o', 'owned_by' => 'openai', 'in_per_1m' => 0, 'out_per_1m' => 0],
            ],
        ]);
    }

    public function designMemoryShow(): JsonResponse
    {
        $row = AiSetting::query()->where('key', 'design_memory')->first();

        return response()->json($row?->value ?? $this->emptyDesignMemory());
    }

    public function designMemoryStore(Request $request): JsonResponse
    {
        $merged = array_merge($this->emptyDesignMemory(), $request->all(), [
            'updated_at' => now()->toIso8601String(),
        ]);
        AiSetting::query()->updateOrCreate(['key' => 'design_memory'], ['value' => $merged]);

        return response()->json($merged);
    }

    public function designMemoryExtract(): JsonResponse
    {
        $memory = array_merge($this->emptyDesignMemory(), [
            'source' => 'stub',
            'updated_at' => now()->toIso8601String(),
            'palette' => ['primary' => '#0f172a', 'accent' => '#2563eb'],
        ]);
        AiSetting::query()->updateOrCreate(['key' => 'design_memory'], ['value' => $memory]);

        return response()->json($memory);
    }

    public function designMemoryReset(): JsonResponse
    {
        $memory = $this->emptyDesignMemory();
        AiSetting::query()->updateOrCreate(['key' => 'design_memory'], ['value' => $memory]);

        return response()->json($memory);
    }

    private function load(): array
    {
        $row = AiSetting::query()->where('key', 'main')->first();
        $settings = self::defaultSettings();
        if ($row && is_array($row->value)) {
            $settings = array_merge($settings, $row->value);
        }

        return $this->publicSettings($settings);
    }

    private function publicSettings(array $settings): array
    {
        foreach (['grok', 'gemini', 'openai', 'gapgpt'] as $p) {
            $key = "{$p}_api_key";
            $has = filled($settings[$key] ?? null);
            $settings["has_{$p}_key"] = $has;
            $settings["{$p}_api_key_masked"] = $has ? '••••••••' : '';
            unset($settings[$key]);
        }

        return $settings;
    }

    private function emptyDesignMemory(): array
    {
        return [
            'palette' => [],
            'kit_color_ids' => [],
            'radius' => [],
            'spacing' => [],
            'shadow' => '',
            'button_style' => '',
            'approved_archetypes' => [],
            'source' => '',
            'locked' => false,
            'updated_at' => now()->toIso8601String(),
        ];
    }
}

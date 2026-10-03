<?php

namespace Modules\AiContent\Services;

use Illuminate\Support\Facades\Http;
use Modules\AiContent\Entities\AiSetting;

/**
 * Shared ERP assistant. Uses the AiContent provider settings when a key exists,
 * and a deterministic heuristic otherwise so CRM, PM, and content keep working.
 */
class ErpAiAssistant
{
    /**
     * @param  array<string, mixed>  $context
     * @return array{source: string, purpose: string, text: string, structured: array<string, mixed>, provider: string|null}
     */
    public function assist(string $purpose, array $context = []): array
    {
        $settings = $this->settings();
        $remote = $this->tryProvider($purpose, $context, $settings);
        if ($remote !== null) {
            $fallback = $this->heuristic($purpose, $context);

            return [
                'source' => 'provider',
                'purpose' => $purpose,
                'text' => $remote['text'],
                'structured' => $remote['structured'] !== [] ? $remote['structured'] : $fallback['structured'],
                'provider' => $remote['provider'],
            ];
        }

        $local = $this->heuristic($purpose, $context);

        return [
            'source' => 'heuristic',
            'purpose' => $purpose,
            'text' => $local['text'],
            'structured' => $local['structured'],
            'provider' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function settings(): array
    {
        $row = AiSetting::query()->where('key', 'main')->first();
        $stored = is_array($row?->value) ? $row->value : [];

        return array_merge([
            'default_provider' => 'gapgpt',
            'gapgpt_model' => 'gpt-4o-mini',
            'openai_model' => 'gpt-4o-mini',
            'language' => 'fa',
        ], $stored);
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $settings
     * @return array{text: string, structured: array<string, mixed>, provider: string}|null
     */
    private function tryProvider(string $purpose, array $context, array $settings): ?array
    {
        $candidates = [
            [
                'name' => 'gapgpt',
                'key' => (string) ($settings['gapgpt_api_key'] ?? env('GAPGPT_API_KEY', '')),
                'model' => (string) ($settings['gapgpt_model'] ?: 'gpt-4o-mini'),
                'base' => rtrim((string) env('GAPGPT_BASE_URL', 'https://api.gapgpt.app/v1'), '/'),
            ],
            [
                'name' => 'openai',
                'key' => (string) ($settings['openai_api_key'] ?? env('OPENAI_API_KEY', '')),
                'model' => (string) ($settings['openai_model'] ?: 'gpt-4o-mini'),
                'base' => rtrim((string) env('OPENAI_BASE_URL', 'https://api.openai.com/v1'), '/'),
            ],
        ];

        $preferred = (string) ($settings['default_provider'] ?? 'gapgpt');
        usort($candidates, fn ($a, $b) => ($a['name'] === $preferred ? 0 : 1) <=> ($b['name'] === $preferred ? 0 : 1));

        $prompt = $this->prompt($purpose, $context, (string) ($settings['language'] ?? 'fa'));
        foreach ($candidates as $candidate) {
            if ($candidate['key'] === '') {
                continue;
            }
            try {
                $response = Http::timeout(25)
                    ->withToken($candidate['key'])
                    ->acceptJson()
                    ->post($candidate['base'].'/chat/completions', [
                        'model' => $candidate['model'],
                        'temperature' => 0.3,
                        'messages' => [
                            ['role' => 'system', 'content' => 'You are the Webino ERP assistant. Reply with concise JSON: {"text":"...","structured":{}}. No markdown.'],
                            ['role' => 'user', 'content' => $prompt],
                        ],
                    ]);
            } catch (\Throwable) {
                continue;
            }
            if (! $response->successful()) {
                continue;
            }
            $content = (string) data_get($response->json(), 'choices.0.message.content', '');
            if ($content === '') {
                continue;
            }
            $parsed = json_decode($this->extractJson($content), true);

            return [
                'text' => is_array($parsed) ? (string) ($parsed['text'] ?? $content) : $content,
                'structured' => is_array($parsed) && is_array($parsed['structured'] ?? null) ? $parsed['structured'] : [],
                'provider' => $candidate['name'],
            ];
        }

        return null;
    }

    private function extractJson(string $content): string
    {
        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        if ($start === false || $end === false || $end <= $start) {
            return $content;
        }

        return substr($content, $start, $end - $start + 1);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function prompt(string $purpose, array $context, string $language): string
    {
        return 'purpose='.$purpose.'; language='.$language.'; context='.json_encode($context, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{text: string, structured: array<string, mixed>}
     */
    private function heuristic(string $purpose, array $context): array
    {
        return match ($purpose) {
            'lead_suggest' => $this->leadSuggest($context),
            'summarize_note' => $this->summarize($context),
            'email_draft' => $this->emailDraft($context),
            'deal_risk' => $this->dealRisk($context),
            'content_brief' => $this->contentBrief($context),
            'task_plan' => $this->taskPlan($context),
            default => [
                'text' => 'No assistant profile for this purpose.',
                'structured' => ['purpose' => $purpose],
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{text: string, structured: array<string, mixed>}
     */
    private function leadSuggest(array $context): array
    {
        $score = 20;
        $reasons = [];
        if (filled($context['email'] ?? null)) {
            $score += 20;
            $reasons[] = 'email';
        }
        if (filled($context['mobile'] ?? null)) {
            $score += 25;
            $reasons[] = 'mobile';
        }
        if (filled($context['company'] ?? null)) {
            $score += 15;
            $reasons[] = 'company';
        }
        if (filled($context['utm_campaign'] ?? null)) {
            $score += 10;
            $reasons[] = 'campaign';
        }
        $score = min(100, $score);
        $band = $score >= 70 ? 'hot' : ($score >= 45 ? 'warm' : 'cold');

        return [
            'text' => 'Suggested follow-up for a '.$band.' lead (score '.$score.'). Call within one business day and confirm the need.',
            'structured' => ['score' => $score, 'band' => $band, 'reasons' => $reasons, 'next_step' => 'call'],
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{text: string, structured: array<string, mixed>}
     */
    private function summarize(array $context): array
    {
        $text = trim((string) ($context['text'] ?? ''));
        $sentences = preg_split('/(?<=[.!؟\n])\s+/u', $text) ?: [];
        $summary = trim(implode(' ', array_slice(array_filter($sentences), 0, 2)));
        if ($summary === '') {
            $summary = 'No note text was provided.';
        }
        $actions = [];
        if (preg_match_all('/(?:باید|لازم است|please|todo|اقدام)\s+(.{3,80})/ui', $text, $matches)) {
            $actions = array_values(array_slice($matches[1], 0, 3));
        }

        return [
            'text' => $summary,
            'structured' => ['summary' => $summary, 'actions' => $actions],
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{text: string, structured: array<string, mixed>}
     */
    private function emailDraft(array $context): array
    {
        $name = (string) ($context['name'] ?? 'مشتری گرامی');
        $topic = (string) ($context['topic'] ?? 'پیگیری همکاری');
        $locale = (string) ($context['locale'] ?? 'fa');
        if ($locale === 'fa') {
            $text = "سلام {$name}\n\nدرباره {$topic} این یادداشت را می‌فرستم. اگر زمان کوتاهی برای تماس دارید، خوشحال می‌شوم هماهنگ کنیم.\n\nبا احترام";
        } else {
            $text = "Hello {$name},\n\nI am writing about {$topic}. If you have a few minutes this week, I would like to continue the conversation.\n\nRegards";
        }

        return [
            'text' => $text,
            'structured' => ['subject' => $topic, 'locale' => $locale],
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{text: string, structured: array<string, mixed>}
     */
    private function dealRisk(array $context): array
    {
        $amount = (float) ($context['amount'] ?? 0);
        $probability = (float) ($context['probability'] ?? 0);
        $ageDays = (int) ($context['age_days'] ?? 0);
        $risk = 25;
        $reasons = [];
        if ($probability < 30) {
            $risk += 25;
            $reasons[] = 'low_probability';
        }
        if ($ageDays > 30) {
            $risk += 20;
            $reasons[] = 'stale';
        }
        if ($amount >= 500000000 && $probability < 50) {
            $risk += 15;
            $reasons[] = 'large_uncommitted';
        }
        $risk = min(100, $risk);
        $level = $risk >= 70 ? 'high' : ($risk >= 40 ? 'medium' : 'low');

        return [
            'text' => 'Deal risk is '.$level.' ('.$risk.').',
            'structured' => ['risk' => $risk, 'level' => $level, 'reasons' => $reasons],
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{text: string, structured: array<string, mixed>}
     */
    private function contentBrief(array $context): array
    {
        $title = (string) ($context['title'] ?? 'محتوا');
        $network = (string) ($context['network'] ?? 'blog');
        $outline = [
            'زاویه: '.$title,
            'شبکه: '.$network,
            'شروع با مسئله مشتری',
            'یک مثال کوتاه',
            'دعوت به اقدام',
        ];

        return [
            'text' => implode("\n", $outline),
            'structured' => ['title' => $title, 'network' => $network, 'outline' => $outline],
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{text: string, structured: array<string, mixed>}
     */
    private function taskPlan(array $context): array
    {
        $titles = array_values(array_filter((array) ($context['titles'] ?? [])));
        if ($titles === []) {
            $titles = ['تعریف محدوده', 'اجرای کار', 'بازبینی'];
        }

        return [
            'text' => 'Suggested order: '.implode(' -> ', $titles),
            'structured' => ['order' => $titles],
        ];
    }
}

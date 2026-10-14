<?php

namespace Modules\AiContent\Services;

use Illuminate\Support\Str;
use Modules\AiContent\Entities\AiJob;

/**
 * Content assist for every site editor (builder inspector, Marketing admin forms).
 * Runs on the shared ErpAiAssistant provider config and logs each call to ai_jobs
 * (job_type content_assist) with tokens and cost. It only returns suggestions; it never writes content.
 */
class ContentAssist
{
    /** Actions and whether they need a provider (false = a deterministic local fallback exists). */
    public const ACTIONS = [
        'generate' => true, 'rewrite' => true, 'shorten' => false, 'expand' => true, 'tone' => true,
        'translate' => true, 'seo_title' => false, 'seo_description' => false, 'slug' => false,
        'excerpt' => false, 'read_time' => false, 'alt_text' => true, 'faq' => true, 'outline' => true,
        'case_study' => true, 'page_draft' => true,
    ];

    public const TONES = ['formal' => 'رسمی و حرفه‌ای', 'friendly' => 'صمیمی و گرم', 'persuasive' => 'متقاعدکننده و فروش‌محور', 'simple' => 'ساده و روان برای عموم', 'expert' => 'تخصصی و دقیق'];

    public function __construct(private readonly ErpAiAssistant $ai) {}

    /**
     * @param  array{action: string, surface: string, field?: string|null, text?: string|null, format?: string|null, tone?: string|null, target_locale?: string|null, context?: array<string, mixed>|null, target_id?: int|null}  $in
     * @return array{suggestion: string, structured: array<string, mixed>, source: string, job_id: int}
     */
    public function run(array $in): array
    {
        $action = $in['action'];
        $text = trim((string) ($in['text'] ?? ''));
        $job = AiJob::query()->create([
            'job_type' => 'content_assist', 'target_type' => $in['surface'].($in['field'] ? '.'.$in['field'] : ''),
            'target_id' => (int) ($in['target_id'] ?? 0), 'status' => 'running', 'attempts' => 1, 'started_at' => now(),
            'payload' => ['action' => $action, 'field' => $in['field'] ?? null, 'tone' => $in['tone'] ?? null, 'target_locale' => $in['target_locale'] ?? null, 'chars' => mb_strlen($text)],
        ]);

        $local = $this->local($action, $text, $in);
        if ($local !== null && (! self::ACTIONS[$action] && ! $this->ai->hasProvider() || $action === 'read_time')) {
            return $this->finish($job, $local, [], 'local', null, null, 0, 0);
        }

        [$system, $user, $json] = $this->prompt($action, $text, $in);
        $res = $this->ai->complete($system, $user, $action === 'slug' ? 0.1 : 0.6);
        if ($res === null) {
            if ($local !== null) {
                return $this->finish($job, $local, [], 'local', null, null, 0, 0);
            }
            $job->update(['status' => 'failed', 'error_message' => 'provider_unavailable', 'finished_at' => now()]);
            abort(response()->json(['message' => 'سرویس هوش مصنوعی پیکربندی نشده یا در دسترس نیست. کلید ارائه‌دهنده را در «تنظیمات هوش مصنوعی» وارد کنید.', 'job_id' => $job->id], 503));
        }

        $out = trim($res['text']);
        $structured = [];
        if ($json) {
            $structured = json_decode($this->jsonPart($out), true) ?: [];
            $out = $this->flatten($action, $structured) ?: $out;
        } else {
            $out = trim(preg_replace('/^```[a-z]*\s*|\s*```$/i', '', $out), " \n\"«»");
        }
        if ($action === 'slug') {
            $out = $this->slugify($out);
        }

        return $this->finish($job, $out, $structured, 'provider', $res['provider'], $res['model'], $res['tokens_in'], $res['tokens_out']);
    }

    private function finish(AiJob $job, string $text, array $structured, string $source, ?string $provider, ?string $model, int $in, int $out): array
    {
        $settings = $this->ai->settingsSnapshot();
        $usd = (float) ($settings['usd_to_toman'] ?? 900000);
        // Same per-1M rates as SettingsController::costEstimate (gpt-4o-mini class).
        $cost = round(($in * 0.15 + $out * 0.6) / 1_000_000 * $usd, 2);
        $job->update([
            'status' => 'done', 'provider' => $provider ?? 'local', 'model' => $model ?? 'heuristic',
            'tokens_in' => $in, 'tokens_out' => $out, 'cost_toman' => $cost, 'finished_at' => now(),
            'result_summary' => Str::limit($text, 240),
        ]);

        return ['suggestion' => $text, 'structured' => $structured, 'source' => $source, 'job_id' => $job->id, 'cost_toman' => $cost];
    }

    /** Deterministic results that need no provider. */
    private function local(string $action, string $text, array $in): ?string
    {
        $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($text)));
        $title = trim((string) data_get($in, 'context.title', ''));

        return match ($action) {
            'read_time' => (string) max(1, (int) ceil(count(preg_split('/\s+/u', $plain, -1, PREG_SPLIT_NO_EMPTY) ?: []) / 200)),
            'slug' => $this->slugify($text !== '' ? $text : $title) ?: null,
            'seo_title' => ($t = $title ?: $plain) !== '' ? Str::limit($t, 57, '…') : null,
            'seo_description', 'excerpt' => $plain !== '' ? $this->sentences($plain, $action === 'excerpt' ? 220 : 155) : null,
            'shorten' => $plain !== '' ? $this->sentences($plain, max(80, (int) (mb_strlen($plain) * 0.6))) : null,
            default => null,
        };
    }

    private function sentences(string $plain, int $max): string
    {
        $out = '';
        foreach (preg_split('/(?<=[.!؟?])\s+/u', $plain) ?: [] as $s) {
            if (mb_strlen($out.' '.$s) > $max) {
                break;
            }
            $out = trim($out.' '.$s);
        }

        return $out !== '' ? $out : Str::limit($plain, $max - 1, '…');
    }

    /** Lowercase ASCII slug when possible; Persian text keeps its letters joined by dashes. */
    public function slugify(string $s): string
    {
        $s = trim(mb_strtolower(strip_tags($s)));
        $ascii = Str::slug($s);
        if ($ascii !== '' && preg_match('/[a-z]/', $ascii)) {
            return Str::limit($ascii, 80, '');
        }
        $s = preg_replace('/[^\p{L}\p{N}]+/u', '-', $s);

        return trim(mb_substr((string) $s, 0, 80), '-');
    }

    /** @return array{0: string, 1: string, 2: bool} system, user, expects JSON */
    private function prompt(string $action, string $text, array $in): array
    {
        $html = ($in['format'] ?? 'text') === 'html';
        $locale = ($in['target_locale'] ?? 'fa') === 'en' ? 'en' : 'fa';
        $ctx = array_filter((array) ($in['context'] ?? []), fn ($v) => is_scalar($v) && trim((string) $v) !== '');
        $ctxText = $ctx ? "\nبافت (برای درک موضوع؛ عیناً تکرار نکن):\n".collect($ctx)->map(fn ($v, $k) => "- {$k}: ".Str::limit((string) $v, 600))->implode("\n") : '';
        $system = 'تو ویراستار و نویسنده ارشد محتوای فارسی «وبینا» (آژانس طراحی سایت، برند و رشد) هستی. '
            .'فارسی معیار و روان بنویس، با نیم‌فاصله درست (می‌شود، طراحی‌ها)، اعداد فارسی، علائم نگارشی فارسی (، ؛ ؟ « »)، بدون اغراق و ادعای ساختگی؛ '
            .'هیچ آمار، نام مشتری یا نتیجه‌ای را که در متن ورودی نیست از خودت نساز. '
            .($html ? 'خروجی HTML تمیز (فقط p, h2, h3, ul, ol, li, strong, em, a) بدون html/body. ' : 'خروجی متن ساده بدون Markdown. ')
            .'فقط خروجی نهایی را بده، بدون توضیح اضافه.';
        $field = (string) ($in['field'] ?? 'متن');
        $tone = self::TONES[$in['tone'] ?? ''] ?? self::TONES['formal'];
        $src = "متن فعلی:\n«{$text}»";
        $user = match ($action) {
            'generate' => "برای فیلد «{$field}» محتوای مناسب بنویس.".($text !== '' ? " یادداشت/ایده نویسنده: «{$text}»" : ''),
            'rewrite' => "این متن را با حفظ معنا، روان‌تر و حرفه‌ای‌تر بازنویسی کن.\n{$src}",
            'shorten' => "این متن را با حفظ پیام اصلی حدوداً نصف کن.\n{$src}",
            'expand' => "این متن را با جزئیات کاربردی و مثال (بدون ادعای ساختگی) حدوداً دو برابر کن.\n{$src}",
            'tone' => "لحن این متن را {$tone} کن، معنا را حفظ کن.\n{$src}",
            'translate' => $locale === 'en'
                ? "Translate into natural, professional English (keep HTML tags if any). Output only the translation.\n«{$text}»"
                : "این متن را به فارسی روان و طبیعی (نه ترجمه‌ای) برگردان؛ تگ‌های HTML را حفظ کن.\n«{$text}»",
            'seo_title' => "یک عنوان سئو (حداکثر ۶۰ نویسه) جذاب و دقیق، با کلیدواژه اصلی در ابتدا بنویس.\n{$src}",
            'seo_description' => "یک توضیح متا (۱۲۰ تا ۱۵۵ نویسه) با دعوت به اقدام نرم بنویس.\n{$src}",
            'slug' => "یک نامک (slug) انگلیسی کوتاه، حروف کوچک و با خط تیره برای این عنوان بده (۳ تا ۶ کلمه). فقط نامک.\n«{$text}»",
            'excerpt' => "یک خلاصه ۱ تا ۲ جمله‌ای (حداکثر ۲۲۰ نویسه) برای کارت فهرست بنویس.\n{$src}",
            'alt_text' => "برای تصویر یک متن جایگزین (alt) فارسی توصیفی، حداکثر ۱۲۵ نویسه، بدون «تصویرِ» در ابتدا بنویس. نشانی/نام فایل و بافت: «{$text}»",
            'faq' => "بر اساس این موضوع/متن ۵ پرسش متداول واقعی مشتری و پاسخ کوتاه دقیق بنویس. فقط JSON: {\"items\":[{\"q\":\"...\",\"a\":\"...\"}]}\n{$src}",
            'outline' => "برای این دوره آموزشی سرفصل مرحله‌ای (۵ تا ۸ درس) و خروجی‌های یادگیری بنویس. فقط JSON: {\"lessons\":[{\"title\":\"...\",\"summary\":\"...\"}],\"outcomes\":[\"...\"]}\n{$src}",
            'case_study' => "از این یادداشت‌ها متن نمونه‌کار بساز. فقط از واقعیت‌های داده‌شده استفاده کن و اگر نتیجه عددی نیست، عدد نساز. فقط JSON: {\"challenge\":\"...\",\"solution\":\"...\",\"results\":\"...\"}\n{$src}",
            'page_draft' => "برای یک صفحه سایت بر اساس این بریف پیش‌نویس ساختار و متن بنویس (۴ تا ۷ بخش؛ اولی هیرو). فقط JSON: {\"title\":\"...\",\"seo_title\":\"...\",\"seo_description\":\"...\",\"sections\":[{\"title\":\"...\",\"subtitle\":\"...\",\"text\":\"...\"}]}\nبریف: «{$text}»",
        };
        $json = in_array($action, ['faq', 'outline', 'case_study', 'page_draft'], true);

        return [$system.($json ? ' پاسخ فقط یک شیء JSON معتبر است.' : ''), $user.$ctxText, $json];
    }

    private function jsonPart(string $s): string
    {
        $a = strpos($s, '{');
        $b = strrpos($s, '}');

        return $a !== false && $b !== false && $b > $a ? substr($s, $a, $b - $a + 1) : $s;
    }

    /** Plain-text rendering of structured results (for single-field apply). */
    private function flatten(string $action, array $d): string
    {
        return match ($action) {
            'faq' => collect($d['items'] ?? [])->map(fn ($i) => ($i['q'] ?? '').'|'.($i['a'] ?? ''))->implode("\n"),
            'outline' => collect($d['outcomes'] ?? [])->implode("\n"),
            'case_study' => trim(($d['challenge'] ?? '')."\n\n".($d['solution'] ?? '')."\n\n".($d['results'] ?? '')),
            'page_draft' => collect($d['sections'] ?? [])->map(fn ($s) => ($s['title'] ?? ''))->implode("\n"),
            default => '',
        };
    }
}

<?php

namespace Modules\Marketing\Services\Builder;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Crm\Entities\CrmLead;
use Modules\Crm\Entities\CrmStatus;
use Modules\Marketing\Entities\BuilderTemplate;
use Modules\Marketing\Entities\MarketingBlogPost;
use Modules\Marketing\Entities\MarketingPage;

/**
 * Server-side actions of a builder "form" widget (captcha, CRM action, webhook).
 *
 * The props are never taken from the request: the submit only names the widget id, and the
 * props are read from the *published* builder document that contains it, so a visitor can't
 * point the webhook at an arbitrary URL or pick a CRM action.
 */
class FormWidgetActions
{
    public const CRM_ACTIONS = ['lead', 'contact', 'deal', 'custom'];

    public const WEBHOOK_TIMEOUT = 5;

    /** @return array{captcha: bool, webhookUrl: string, crmAction: string, crmKey: string}|null */
    public static function props(?string $widgetId, string $formKey): ?array
    {
        if (! is_string($widgetId) || ! preg_match('/^[A-Za-z0-9_-]{1,64}$/', $widgetId)) {
            return null;
        }
        $needle = '%"'.$widgetId.'"%';
        $docs = collect()
            ->merge(MarketingPage::query()->where('builder_published', 'like', $needle)->limit(5)->pluck('builder_published'))
            ->merge(MarketingBlogPost::query()->where('builder_published', 'like', $needle)->limit(5)->pluck('builder_published'))
            ->merge(BuilderTemplate::query()->where('published', 'like', $needle)->limit(5)->pluck('published'));

        foreach ($docs as $doc) {
            $node = is_array($doc) ? self::find($doc, $widgetId) : null;
            if (! $node) {
                continue;
            }
            $props = is_array($node['props'] ?? null) ? $node['props'] : [];
            $key = trim((string) ($props['formKey'] ?? '')) ?: 'contact';
            if ($key !== $formKey) {
                return null;
            }
            $action = (string) ($props['crmAction'] ?? '');

            return [
                'captcha' => ($props['captcha'] ?? false) === true,
                'webhookUrl' => trim((string) ($props['webhookUrl'] ?? '')),
                'crmAction' => in_array($action, self::CRM_ACTIONS, true) ? $action : '',
                'crmKey' => Str::limit(trim((string) ($props['crmKey'] ?? '')), 100, ''),
            ];
        }

        return null;
    }

    private static function find(array $tree, string $id): ?array
    {
        if (($tree['id'] ?? null) === $id && ($tree['type'] ?? null) === 'form') {
            return $tree;
        }
        foreach ($tree as $child) {
            if (is_array($child) && ($hit = self::find($child, $id))) {
                return $hit;
            }
        }

        return null;
    }

    /** http(s) only, no credentials, no private / loopback / link-local / reserved targets. Returns the pinned IP. */
    public static function webhookTarget(string $url): ?array
    {
        $parts = parse_url(trim($url));
        if (! is_array($parts) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(trim((string) $parts['host'], '[]'));
        if (! in_array($scheme, ['http', 'https'], true) || PublicUrlGuard::isBlockedHost($host)) {
            return null;
        }
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (@gethostbynamel($host) ?: []);
        if ($ips === []) {
            return null;
        }
        foreach ($ips as $ip) {
            if (PublicUrlGuard::isBlockedIp($ip)) {
                return null;
            }
        }
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));

        return ['host' => $host, 'port' => $port, 'ip' => $ips[0]];
    }

    /** @param array<string, mixed> $payload */
    public static function run(array $props, string $formKey, array $payload, string $notes): void
    {
        if ($props['crmAction'] !== '') {
            try {
                self::crm($props['crmAction'], $props['crmKey'], $formKey, $payload, $notes);
            } catch (\Throwable $e) {
                Log::warning('form_widget_crm_failed', ['form' => $formKey, 'error' => $e->getMessage()]);
            }
        }
        $url = $props['webhookUrl'];
        if ($url === '') {
            return;
        }
        $target = self::webhookTarget($url);
        if (! $target) {
            Log::warning('form_widget_webhook_blocked', ['form' => $formKey]);

            return;
        }
        $body = ['form_key' => $formKey, 'payload' => $payload, 'crm_action' => $props['crmAction'] ?: null, 'submitted_at' => now()->toIso8601String()];
        dispatch(function () use ($url, $target, $body): void {
            try {
                Http::timeout(self::WEBHOOK_TIMEOUT)->connectTimeout(3)->withoutRedirecting()->asJson()
                    // Pin the vetted IP so DNS can't be rebound to an internal address between check and request.
                    ->withOptions(['curl' => [CURLOPT_RESOLVE => [$target['host'].':'.$target['port'].':'.$target['ip']]]])
                    ->post($url, $body);
            } catch (\Throwable $e) {
                Log::warning('form_widget_webhook_failed', ['error' => $e->getMessage()]);
            }
        })->afterResponse();
    }

    /** lead / contact / deal land as a CRM lead (sales converts it); custom fires an event listeners can handle. */
    private static function crm(string $action, string $key, string $formKey, array $payload, string $notes): void
    {
        if ($action === 'custom') {
            if ($key !== '') {
                event('marketing.form.crm_action', [['key' => $key, 'form_key' => $formKey, 'payload' => $payload]]);
            }

            return;
        }
        $name = trim(preg_replace('/\s+/u', ' ', (string) ($payload['name'] ?? '')) ?? '');
        $parts = explode(' ', $name, 2);
        $str = fn (string $k, int $max) => ($v = trim((string) ($payload[$k] ?? ''))) === '' ? null : mb_substr($v, 0, $max);
        $label = ['lead' => 'سرنخ', 'contact' => 'مخاطب', 'deal' => 'معامله'][$action];
        $status = CrmStatus::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->first()
            ?? CrmStatus::query()->create(['name' => 'جدید', 'color' => '#0054ff', 'sort_order' => 0, 'is_active' => true]);

        CrmLead::query()->create([
            'topic' => mb_substr($label.' از فرم سایت: '.$formKey, 0, 200),
            'first_name' => mb_substr($parts[0] ?: 'بدون‌نام', 0, 100),
            'last_name' => mb_substr($parts[1] ?? '-', 0, 100),
            'company' => $str('company', 150),
            'email' => $str('email', 150),
            'mobile' => $str('phone', 20) ?? $str('mobile', 20) ?? '-',
            'status_id' => $status->id,
            'description' => $notes,
        ]);
    }
}

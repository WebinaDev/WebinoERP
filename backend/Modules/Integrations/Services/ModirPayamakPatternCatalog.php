<?php

namespace Modules\Integrations\Services;

/**
 * Canonical IPPanel pattern titles: «site name | role | action».
 * Titles are the durable key when the local registry is wiped.
 */
class ModirPayamakPatternCatalog
{
    /** @var array<string, string> */
    public const ACTIONS = [
        'pending_on_create' => 'در انتظار پرداخت',
        'pending_on_status' => 'در انتظار پرداخت',
        'on-hold' => 'در انتظار بررسی',
        'processing' => 'در حال انجام',
        'packaged' => 'بسته‌بندی',
        'sent-to-warehouse' => 'ارسال انبار',
        'courier' => 'پیک',
        'post' => 'پست',
        'tipax' => 'تیپاکس',
        'completed' => 'تکمیل شده',
        'cancelled' => 'لغو شده',
        'failed' => 'ناموفق',
        'refunded' => 'استرداد',
        'user-welcome' => 'خوش‌آمد',
        'stock-low' => 'کمبود موجودی',
        'stock-out' => 'اتمام موجودی',
        'cart-abandoned' => 'سبد رها',
        'order-abandoned' => 'سفارش رها',
        'otp_login' => 'ورود',
        'otp_register' => 'ثبت‌نام',
    ];

    /** @var array<string, string> extra labels → event key */
    private const ALIASES = [
        'تکمیل سفارش' => 'completed',
        'تکمیل شده' => 'completed',
        'لغو' => 'cancelled',
        'در انتظار تایید' => 'on-hold',
        'در حال بررسی' => 'on-hold',
        'بسته بندی' => 'packaged',
        'خوش آمد' => 'user-welcome',
        'خوشامد' => 'user-welcome',
        'otp' => 'otp_login',
        'کد ورود' => 'otp_login',
        'ورود و ثبت نام' => 'otp_login',
        'ورود و ثبت‌نام' => 'otp_login',
    ];

    public static function title(string $site, string $role, string $actionLabel): string
    {
        return trim($site).' | '.self::roleLabel($role).' | '.trim($actionLabel);
    }

    public static function roleLabel(string $role): string
    {
        $role = self::fold($role);
        if (in_array($role, ['admin', 'مدیر', 'order_admin'], true)) {
            return 'مدیر';
        }

        return 'مشتری';
    }

    public static function scopeForRole(string $roleLabel): string
    {
        return self::fold($roleLabel) === 'مدیر' ? 'order_admin' : 'order_customer';
    }

    public static function actionKey(string $label): ?string
    {
        $folded = self::fold($label);
        if ($folded === '') {
            return null;
        }
        if (isset(self::ACTIONS[$label])) {
            return $label;
        }
        foreach (self::ALIASES as $alias => $key) {
            if (self::fold($alias) === $folded) {
                return $key;
            }
        }
        foreach (self::ACTIONS as $key => $persian) {
            if (self::fold($persian) === $folded || $folded === self::fold($key)) {
                return $key;
            }
        }

        return null;
    }

    public static function actionLabel(string $eventKey): string
    {
        return self::ACTIONS[$eventKey] ?? $eventKey;
    }

    /**
     * @return array{site: string, role: string, action: string, event_key: string}|null
     */
    public static function parseTitle(string $title): ?array
    {
        $title = trim(str_replace(['｜', '–', '—'], ['|', '-', '-'], $title));
        if ($title === '') {
            return null;
        }
        $parts = preg_split('/\s*[|\-]\s*/u', $title) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts), fn ($p) => $p !== ''));
        if ($parts === []) {
            return null;
        }

        $role = '';
        $action = '';
        $eventKey = '';
        $siteParts = [];
        foreach ($parts as $part) {
            $folded = self::fold($part);
            if (in_array($folded, ['مدیر', 'مشتری', 'admin', 'customer'], true)) {
                $role = self::roleLabel($part);
                continue;
            }
            $key = self::actionKey($part);
            if ($key !== null && $action === '') {
                $action = self::actionLabel($key);
                $eventKey = $key;
                continue;
            }
            $siteParts[] = $part;
        }
        if ($eventKey === '') {
            return null;
        }
        if ($role === '' && in_array($eventKey, ['otp_login', 'otp_register'], true)) {
            $role = 'مشتری';
        }
        if ($role === '') {
            return null;
        }

        return [
            'site' => trim(implode(' ', $siteParts)),
            'role' => $role,
            'action' => $action,
            'event_key' => $eventKey,
        ];
    }

    /**
     * Match a provider pattern to a licensed site even when the title is not canonical yet.
     *
     * @param  list<array{domain: string, name: string}>  $sites
     * @return array{site: string, domain: string, role: string, action: string, event_key: string}|null
     */
    public static function identify(string $title, string $message, string $website, array $sites): ?array
    {
        $parsed = self::parseTitle($title);
        $site = null;
        if ($parsed !== null && $parsed['site'] !== '') {
            $site = self::matchSiteName($parsed['site'], $sites);
        }
        if ($site === null) {
            $host = self::websiteHost($website);
            if ($host !== '') {
                $site = self::matchSiteName($host, $sites);
            }
        }
        $eventKey = $parsed['event_key'] ?? '';
        $role = $parsed['role'] ?? '';
        if ($eventKey === '') {
            $eventKey = self::eventInText(trim($title.' '.$message)) ?? '';
        }
        if ($role === '') {
            $role = self::roleInText($title.' '.$message) ?? '';
        }
        if ($role === '' && in_array($eventKey, ['otp_login', 'otp_register'], true)) {
            $role = 'مشتری';
        }
        if ($site === null || $eventKey === '' || $role === '') {
            return null;
        }

        return [
            'site' => $site['name'] !== '' ? $site['name'] : $site['domain'],
            'domain' => $site['domain'],
            'role' => $role,
            'action' => self::actionLabel($eventKey),
            'event_key' => $eventKey,
        ];
    }

    /**
     * @param  list<array{domain: string, name: string}>  $sites
     * @return array{domain: string, name: string}|null
     */
    public static function matchSiteName(string $siteName, array $sites): ?array
    {
        if (trim($siteName) === '') {
            return null;
        }
        $exact = null;
        $fuzzy = null;
        foreach ($sites as $site) {
            if (self::fold($siteName) === self::fold($site['name'])
                || self::fold($siteName) === self::fold($site['domain'])) {
                $exact = $site;
                break;
            }
            if ($fuzzy === null && (
                self::namesMatch($siteName, $site['name'])
                || self::namesMatch($siteName, $site['domain'])
            )) {
                $fuzzy = $site;
            }
        }

        return $exact ?? $fuzzy;
    }

    public static function websiteHost(string $website): string
    {
        $website = trim($website);
        if ($website === '') {
            return '';
        }
        if (! str_contains($website, '://')) {
            $website = 'https://'.$website;
        }
        $host = parse_url($website, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return '';
        }
        $host = strtolower($host);

        return str_starts_with($host, 'www.') ? substr($host, 4) : $host;
    }

    public static function eventInText(string $text): ?string
    {
        $folded = self::fold($text);
        if ($folded === '') {
            return null;
        }
        $best = null;
        $bestLen = 0;
        $candidates = self::ALIASES;
        foreach (self::ACTIONS as $key => $label) {
            $candidates[$label] = $key;
            $candidates[$key] = $key;
        }
        foreach ($candidates as $label => $key) {
            $needle = self::fold((string) $label);
            if (mb_strlen($needle) < 3 || ! str_contains($folded, $needle)) {
                continue;
            }
            if (mb_strlen($needle) > $bestLen) {
                $best = $key;
                $bestLen = mb_strlen($needle);
            }
        }

        return $best;
    }

    public static function roleInText(string $text): ?string
    {
        $folded = self::fold($text);
        if (str_contains($folded, self::fold('مدیر')) || str_contains($folded, 'admin')) {
            return 'مدیر';
        }
        if (str_contains($folded, self::fold('مشتری')) || str_contains($folded, 'customer')) {
            return 'مشتری';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public static function fillTemplate(string $template, array $params): string
    {
        $text = $template;
        foreach ($params as $key => $value) {
            if (! is_string($key) || ! is_scalar($value)) {
                continue;
            }
            $text = str_replace(['%'.$key.'%', '{'.$key.'}'], (string) $value, $text);
        }

        return $text;
    }

    public static function namesMatch(string $left, string $right): bool
    {
        $a = self::fold($left);
        $b = self::fold($right);
        if ($a === '' || $b === '') {
            return false;
        }
        if ($a === $b) {
            return true;
        }
        $short = mb_strlen($a) <= mb_strlen($b) ? $a : $b;
        $long = $short === $a ? $b : $a;
        if (mb_strlen($short) < 4) {
            return false;
        }
        if (! str_contains($long, $short)) {
            return false;
        }

        return mb_strlen($short) / mb_strlen($long) >= 0.5;
    }

    /**
     * @return list<array{name: string, type: string}>
     */
    public static function variablesFromMessage(string $message): array
    {
        if (! preg_match_all('/%([A-Za-z0-9_]+)%/u', $message, $matches)) {
            return [];
        }
        $out = [];
        foreach (array_unique($matches[1]) as $name) {
            $folded = strtolower($name);
            $out[] = [
                'name' => $name,
                'type' => in_array($folded, ['otp', 'code'], true) ? 'integer' : 'string',
            ];
        }

        return $out;
    }

    public static function fold(string $value): string
    {
        $value = str_replace(["\u{200c}", "\u{200d}", 'ي', 'ك'], ['', '', 'ی', 'ک'], $value);
        $value = preg_replace('/\s+/u', '', $value) ?? $value;

        return mb_strtolower(trim($value));
    }
}

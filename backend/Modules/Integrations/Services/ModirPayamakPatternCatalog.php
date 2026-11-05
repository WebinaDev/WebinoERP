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

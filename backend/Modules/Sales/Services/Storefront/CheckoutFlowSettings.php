<?php

namespace Modules\Sales\Services\Storefront;

use Carbon\CarbonImmutable;

/**
 * Stepped cart and checkout configuration (admin: shop settings, checkout flow).
 *
 * Steps: address -> delivery_time -> shipping -> payment. Every step field has
 * enabled / required / label / order. Delivery time can be off, on for all shipping
 * methods, or on only for listed shipping method ids. Stored in storefront settings `checkout_flow`.
 */
class CheckoutFlowSettings
{
    public const KEY = 'checkout_flow';

    public const STEPS = ['address', 'delivery_time', 'shipping', 'payment'];

    public function __construct(protected StorefrontSettings $settings) {}

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        $f = fn (string $key, string $label, bool $enabled = true, bool $required = false, string $type = 'text', int $order = 0) => compact('key', 'label', 'enabled', 'required', 'type', 'order');

        return [
            'stepped' => true,
            'steps' => [
                ['key' => 'address', 'label' => 'آدرس و اطلاعات گیرنده', 'enabled' => true, 'order' => 1],
                ['key' => 'delivery_time', 'label' => 'زمان ارسال', 'enabled' => false, 'order' => 2],
                ['key' => 'shipping', 'label' => 'روش ارسال', 'enabled' => true, 'order' => 3],
                ['key' => 'payment', 'label' => 'پرداخت', 'enabled' => true, 'order' => 4],
            ],
            'fields' => [
                'address' => [
                    $f('customer_name', 'نام و نام خانوادگی', true, true, 'text', 1),
                    $f('customer_phone', 'شماره موبایل', true, true, 'tel', 2),
                    $f('customer_email', 'ایمیل', true, false, 'email', 3),
                    $f('shipping_state_code', 'استان', true, false, 'select', 4),
                    $f('shipping_city', 'شهر', true, false, 'text', 5),
                    $f('address_line', 'نشانی کامل', true, false, 'textarea', 6),
                    $f('shipping_postcode', 'کد پستی', true, false, 'text', 7),
                    $f('customer_note', 'توضیحات سفارش', true, false, 'textarea', 8),
                ],
                'delivery_time' => [
                    $f('delivery_date', 'روز ارسال', true, true, 'date', 1),
                    $f('delivery_slot', 'بازه زمانی', true, true, 'slot', 2),
                ],
                'shipping' => [
                    $f('shipping_method', 'روش ارسال', true, true, 'radio', 1),
                ],
                'payment' => [
                    $f('payment_gateway', 'درگاه پرداخت', true, true, 'gateways', 1),
                    $f('coupon_code', 'کد تخفیف', true, false, 'code', 2),
                    $f('gift_card_code', 'کارت هدیه', true, false, 'code', 3),
                    $f('order_summary', 'خلاصه سفارش', true, false, 'summary', 4),
                    $f('terms', 'قوانین و مقررات را می‌پذیرم', false, false, 'checkbox', 5),
                ],
            ],
            'delivery_time' => [
                'mode' => 'all',
                'shipping_methods' => [],
                'days_ahead' => 7,
                'min_lead_days' => 0,
                'same_day_cutoff' => '14:00',
                'closed_weekdays' => [5],
                'closed_dates' => [],
                'slots' => [
                    ['id' => 'morning', 'label' => 'صبح ۹ تا ۱۲', 'from' => '09:00', 'to' => '12:00', 'enabled' => true],
                    ['id' => 'noon', 'label' => 'ظهر ۱۲ تا ۱۵', 'from' => '12:00', 'to' => '15:00', 'enabled' => true],
                    ['id' => 'evening', 'label' => 'عصر ۱۵ تا ۱۸', 'from' => '15:00', 'to' => '18:00', 'enabled' => true],
                ],
            ],
            'payment' => [
                'gateway_layout' => 'list',
                'gateway_order' => [],
                'hidden_gateways' => [],
                'terms_url' => '',
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function get(): array
    {
        $stored = $this->settings->get(self::KEY);

        return self::merge(self::defaults(), is_array($stored) ? $stored : []);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function save(array $input): array
    {
        $clean = self::sanitize(self::merge($this->get(), $input));
        $this->settings->save([self::KEY => $clean]);

        return $this->get();
    }

    /** Merchant has saved the flow at least once (required address fields are enforced from then on). */
    public function configured(): bool
    {
        $stored = $this->settings->get(self::KEY);

        return is_array($stored) && $stored !== [];
    }

    /**
     * Stored values over defaults: steps/fields merge by key so new default fields still appear.
     *
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $over
     * @return array<string, mixed>
     */
    public static function merge(array $base, array $over): array
    {
        $out = $base;
        if (array_key_exists('stepped', $over)) {
            $out['stepped'] = (bool) $over['stepped'];
        }
        if (is_array($over['steps'] ?? null)) {
            $out['steps'] = self::mergeRows($base['steps'], $over['steps']);
        }
        if (is_array($over['fields'] ?? null)) {
            foreach (self::STEPS as $step) {
                if (is_array($over['fields'][$step] ?? null)) {
                    $out['fields'][$step] = self::mergeRows($base['fields'][$step] ?? [], $over['fields'][$step]);
                }
            }
        }
        foreach (['delivery_time', 'payment'] as $k) {
            if (is_array($over[$k] ?? null)) {
                $out[$k] = array_replace($base[$k], $over[$k]);
            }
        }
        $out = self::sanitize($out);

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $base
     * @param  array<int|string, mixed>  $over
     * @return list<array<string, mixed>>
     */
    protected static function mergeRows(array $base, array $over): array
    {
        $byKey = [];
        foreach ($base as $row) {
            $byKey[$row['key']] = $row;
        }
        foreach ($over as $k => $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = (string) ($row['key'] ?? (is_string($k) ? $k : ''));
            if ($key === '' || ! isset($byKey[$key])) {
                continue;
            }
            foreach (['label', 'enabled', 'required', 'order'] as $attr) {
                if (array_key_exists($attr, $row) && $row[$attr] !== null) {
                    $byKey[$key][$attr] = $row[$attr];
                }
            }
        }

        return array_values($byKey);
    }

    /**
     * @param  array<string, mixed>  $cfg
     * @return array<string, mixed>
     */
    public static function sanitize(array $cfg): array
    {
        $cfg['stepped'] = (bool) ($cfg['stepped'] ?? true);
        foreach ($cfg['steps'] as $i => $step) {
            $cfg['steps'][$i]['label'] = self::label($step['label'] ?? '');
            $cfg['steps'][$i]['enabled'] = in_array($step['key'], ['address', 'payment'], true) ? true : (bool) ($step['enabled'] ?? false);
            $cfg['steps'][$i]['order'] = max(0, min(99, (int) ($step['order'] ?? $i + 1)));
        }
        usort($cfg['steps'], fn ($a, $b) => $a['order'] <=> $b['order']);
        foreach (self::STEPS as $step) {
            foreach ($cfg['fields'][$step] ?? [] as $i => $field) {
                $locked = in_array($field['key'], ['payment_gateway', 'shipping_method'], true);
                $enabled = $locked ? true : (bool) ($field['enabled'] ?? false);
                $cfg['fields'][$step][$i]['label'] = self::label($field['label'] ?? '');
                $cfg['fields'][$step][$i]['enabled'] = $enabled;
                $cfg['fields'][$step][$i]['required'] = $enabled && (bool) ($field['required'] ?? false);
                $cfg['fields'][$step][$i]['order'] = max(0, min(99, (int) ($field['order'] ?? $i + 1)));
            }
            usort($cfg['fields'][$step], fn ($a, $b) => $a['order'] <=> $b['order']);
        }
        $d = $cfg['delivery_time'];
        $d['mode'] = in_array($d['mode'] ?? 'all', ['all', 'methods'], true) ? $d['mode'] : 'all';
        $d['shipping_methods'] = array_values(array_unique(array_filter(array_map(
            fn ($v) => preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $v),
            (array) ($d['shipping_methods'] ?? [])
        ))));
        $d['days_ahead'] = max(1, min(60, (int) ($d['days_ahead'] ?? 7)));
        $d['min_lead_days'] = max(0, min(30, (int) ($d['min_lead_days'] ?? 0)));
        $d['same_day_cutoff'] = preg_match('/^\d{2}:\d{2}$/', (string) ($d['same_day_cutoff'] ?? '')) ? $d['same_day_cutoff'] : '';
        $d['closed_weekdays'] = array_values(array_unique(array_filter(array_map('intval', (array) ($d['closed_weekdays'] ?? [])), fn ($v) => $v >= 0 && $v <= 6)));
        $d['closed_dates'] = array_values(array_filter(array_map('strval', (array) ($d['closed_dates'] ?? [])), fn ($v) => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)));
        $slots = [];
        foreach ((array) ($d['slots'] ?? []) as $slot) {
            if (! is_array($slot)) {
                continue;
            }
            $id = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) ($slot['id'] ?? '')) ?: 'slot'.(count($slots) + 1);
            $from = preg_match('/^\d{2}:\d{2}$/', (string) ($slot['from'] ?? '')) ? $slot['from'] : '';
            $to = preg_match('/^\d{2}:\d{2}$/', (string) ($slot['to'] ?? '')) ? $slot['to'] : '';
            $slots[] = [
                'id' => $id,
                'label' => self::label($slot['label'] ?? '') ?: trim($from.' '.$to),
                'from' => $from,
                'to' => $to,
                'enabled' => (bool) ($slot['enabled'] ?? true),
            ];
        }
        $d['slots'] = $slots;
        $cfg['delivery_time'] = $d;
        $p = $cfg['payment'];
        $p['gateway_layout'] = in_array($p['gateway_layout'] ?? 'list', ['list', 'grouped', 'cards'], true) ? $p['gateway_layout'] : 'list';
        $p['gateway_order'] = array_values(array_filter(array_map(fn ($v) => preg_replace('/[^a-z0-9_]/', '', (string) $v), (array) ($p['gateway_order'] ?? []))));
        $p['hidden_gateways'] = array_values(array_filter(array_map(fn ($v) => preg_replace('/[^a-z0-9_]/', '', (string) $v), (array) ($p['hidden_gateways'] ?? []))));
        $p['terms_url'] = mb_substr(trim((string) ($p['terms_url'] ?? '')), 0, 300);
        $cfg['payment'] = $p;

        return $cfg;
    }

    protected static function label(mixed $v): string
    {
        // Persian copy rule: no em or en dashes in labels.
        return mb_substr(trim(str_replace(['—', '–'], '،', strip_tags((string) $v))), 0, 120);
    }

    /** @param array<string, mixed> $cfg */
    public static function stepEnabled(array $cfg, string $step): bool
    {
        foreach ($cfg['steps'] as $row) {
            if ($row['key'] === $step) {
                return (bool) $row['enabled'];
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $cfg
     * @return array<string, mixed>|null
     */
    public static function field(array $cfg, string $step, string $key): ?array
    {
        foreach ($cfg['fields'][$step] ?? [] as $row) {
            if ($row['key'] === $key) {
                return $row;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $cfg */
    public static function fieldEnabled(array $cfg, string $step, string $key): bool
    {
        $f = self::field($cfg, $step, $key);

        return $f !== null && $f['enabled'];
    }

    /**
     * Delivery-time step applies to the picked shipping rate?
     *
     * @param  array<string, mixed>  $cfg
     * @param  array<string, mixed>|null  $rate
     */
    public static function deliveryTimeApplies(array $cfg, ?array $rate): bool
    {
        if (! self::stepEnabled($cfg, 'delivery_time')) {
            return false;
        }
        $d = $cfg['delivery_time'];
        if ($d['mode'] === 'all') {
            return true;
        }
        if ($rate === null) {
            return false;
        }
        $ids = $d['shipping_methods'];

        return in_array((string) ($rate['id'] ?? ''), $ids, true);
    }

    /**
     * Selectable delivery days (Y-m-d, Tehran) with their enabled slots.
     *
     * @param  array<string, mixed>  $cfg
     * @return list<array{date: string, weekday: int, slots: list<array<string, mixed>>}>
     */
    public static function availableDays(array $cfg, ?CarbonImmutable $now = null): array
    {
        $d = $cfg['delivery_time'];
        $now ??= CarbonImmutable::now('Asia/Tehran');
        $slots = array_values(array_filter($d['slots'], fn ($s) => $s['enabled']));
        if ($slots === []) {
            return [];
        }
        $out = [];
        $start = $now->startOfDay()->addDays($d['min_lead_days']);
        for ($i = 0; $i < $d['days_ahead'] + 14 && count($out) < $d['days_ahead']; $i++) {
            $day = $start->addDays($i);
            $weekday = (int) $day->dayOfWeek;
            if (in_array($weekday, $d['closed_weekdays'], true) || in_array($day->toDateString(), $d['closed_dates'], true)) {
                continue;
            }
            $daySlots = $slots;
            if ($day->isSameDay($now)) {
                if ($d['same_day_cutoff'] !== '' && $now->format('H:i') >= $d['same_day_cutoff']) {
                    continue;
                }
                $daySlots = array_values(array_filter($slots, fn ($s) => $s['from'] === '' || $s['from'] > $now->format('H:i')));
                if ($daySlots === []) {
                    continue;
                }
            }
            $out[] = ['date' => $day->toDateString(), 'weekday' => $weekday, 'slots' => $daySlots];
        }

        return $out;
    }

    /**
     * Public payload for storefront pages (no admin-only keys).
     *
     * @param  array<string, mixed>  $cfg
     * @return array<string, mixed>
     */
    public static function publicPayload(array $cfg): array
    {
        $fields = [];
        foreach (self::STEPS as $step) {
            $fields[$step] = array_values(array_filter($cfg['fields'][$step], fn ($f) => $f['enabled']));
        }

        return [
            'stepped' => $cfg['stepped'],
            'steps' => array_values(array_filter($cfg['steps'], fn ($s) => $s['enabled'])),
            'fields' => $fields,
            'delivery_time' => [
                'mode' => $cfg['delivery_time']['mode'],
                'shipping_methods' => $cfg['delivery_time']['shipping_methods'],
                'days' => self::stepEnabled($cfg, 'delivery_time') ? self::availableDays($cfg) : [],
            ],
            'payment' => [
                'gateway_layout' => $cfg['payment']['gateway_layout'],
                'coupon_enabled' => self::fieldEnabled($cfg, 'payment', 'coupon_code'),
                'gift_card_enabled' => self::fieldEnabled($cfg, 'payment', 'gift_card_code'),
                'summary_enabled' => self::fieldEnabled($cfg, 'payment', 'order_summary'),
                'terms_required' => (bool) (self::field($cfg, 'payment', 'terms')['required'] ?? false),
                'terms_url' => $cfg['payment']['terms_url'],
            ],
        ];
    }
}

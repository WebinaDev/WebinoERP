<?php

namespace Modules\Sales\Services\Storefront;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Admin-managed shop settings (shipping, checkout fields, cart behaviour). */
class StorefrontSettings
{
    public const DEFAULTS = [
        'shipping_flat_minor' => 500000,
        'free_shipping_over_minor' => 10000000,
        'require_login' => false,
        'require_email' => false,
        'require_address' => false,
        'cash_enabled' => true,
        'installment_enabled' => true,
        'checkout_note' => '',
        'thank_you_text' => 'سفارش شما ثبت شد. جزئیات سفارش در حساب کاربری شما قابل مشاهده است.',
        // Off: one unit price everywhere (no installment pricing layer; cash gateways only).
        'pricing_enabled' => true,
        'gift_cards_enabled' => true,
        // Empty list = one "post" method using shipping_flat_minor / free_shipping_over_minor.
        'shipping_methods' => [],
        // Stepped checkout config (CheckoutFlowSettings); empty = defaults.
        'checkout_flow' => [],
    ];

    /** @var array<string, mixed>|null */
    private ?array $cache = null;

    /** @return array<string, mixed> */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }
        $out = self::DEFAULTS;
        if (Schema::hasTable('sales_storefront_settings')) {
            foreach (DB::table('sales_storefront_settings')->get(['key', 'value']) as $row) {
                if (array_key_exists($row->key, $out)) {
                    $out[$row->key] = json_decode((string) $row->value, true);
                }
            }
        }

        return $this->cache = $out;
    }

    public function get(string $key): mixed
    {
        return $this->all()[$key] ?? null;
    }

    /** @param  array<string, mixed>  $values */
    public function save(array $values): array
    {
        foreach ($values as $key => $value) {
            if (! array_key_exists($key, self::DEFAULTS)) {
                continue;
            }
            $default = self::DEFAULTS[$key];
            if ($key === 'checkout_flow') {
                $value = CheckoutFlowSettings::merge(CheckoutFlowSettings::defaults(), is_array($value) ? $value : []);
            } elseif ($key === 'shipping_methods') {
                $value = self::sanitizeShippingMethods(is_array($value) ? $value : []);
            }
            $value = is_array($default) ? (array) $value : (is_bool($default) ? (bool) $value : (is_int($default) ? max(0, (int) $value) : mb_substr(strip_tags((string) $value), 0, 1000)));
            DB::table('sales_storefront_settings')->updateOrInsert(['key' => $key], ['value' => json_encode($value), 'updated_at' => now(), 'created_at' => now()]);
        }
        $this->cache = null;

        return $this->all();
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return list<array{id: string, title: string, cost_minor: int, free_over_minor: int, enabled: bool, order: int}>
     */
    public static function sanitizeShippingMethods(array $rows): array
    {
        $out = [];
        foreach (array_slice($rows, 0, 20) as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = preg_replace('/[^a-z0-9_\-]/', '', mb_strtolower((string) ($row['id'] ?? ''))) ?: 'method'.($i + 1);
            $title = mb_substr(trim(str_replace(['—', '–'], '،', strip_tags((string) ($row['title'] ?? '')))), 0, 80);
            $out[$id] = [
                'id' => $id,
                'title' => $title !== '' ? $title : $id,
                'cost_minor' => max(0, (int) ($row['cost_minor'] ?? 0)),
                'free_over_minor' => max(0, (int) ($row['free_over_minor'] ?? 0)),
                'enabled' => (bool) ($row['enabled'] ?? true),
                'order' => (int) ($row['order'] ?? $i + 1),
            ];
        }
        $out = array_values($out);
        usort($out, fn ($a, $b) => $a['order'] <=> $b['order']);

        return $out;
    }

    /** Enabled shipping methods; the legacy flat rate when none are configured. */
    public function shippingMethods(): array
    {
        $rows = array_values(array_filter((array) $this->get('shipping_methods'), fn ($m) => is_array($m) && ($m['enabled'] ?? true)));
        if ($rows !== []) {
            return $rows;
        }

        return [[
            'id' => 'post', 'title' => 'ارسال پستی', 'cost_minor' => (int) $this->get('shipping_flat_minor'),
            'free_over_minor' => (int) $this->get('free_shipping_over_minor'), 'enabled' => true, 'order' => 1,
        ]];
    }
}

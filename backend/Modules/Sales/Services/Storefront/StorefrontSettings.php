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
            $value = is_bool($default) ? (bool) $value : (is_int($default) ? max(0, (int) $value) : mb_substr(strip_tags((string) $value), 0, 1000));
            DB::table('sales_storefront_settings')->updateOrInsert(['key' => $key], ['value' => json_encode($value), 'updated_at' => now(), 'created_at' => now()]);
        }
        $this->cache = null;

        return $this->all();
    }
}

<?php

namespace Modules\Sales\Services;

use Modules\Sales\Entities\SalesRahnSetting;

/**
 * Defaults, seed catalog, get/save for رهن‌درصد — ported from WebinoCRM_Rahn_Settings.
 */
class RahnSettingsService
{
    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'T' => 6,
            'm' => 0.60,
            'k' => 0.70,
            'p_min' => 0.05,
            'p_max' => 0.25,
            'p_default' => 0.10,
            's_hat_default' => 100000000,
            'clause_template' => 'هر ماه {F} تومان ثابت + {p} درصد از فروش کل',
            'review' => [
                'enabled' => false,
                'deviation_percent' => 25,
                'consecutive_months' => 3,
            ],
            'sales_definition' => [
                'G' => ['enabled' => true, 'label' => 'فروش ثبت‌شده سایت'],
                'R' => ['enabled' => true, 'label' => 'لغو و مرجوعی قطعی'],
                'D' => ['enabled' => true, 'label' => 'کارمزد درگاه / پلتفرم / تخفیف کانال'],
                'X' => ['enabled' => true, 'label' => 'سفارش تست و فروش خارج از اسکوپ'],
            ],
            'catalog' => self::seedCatalog(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function seedCatalog(): array
    {
        $rows = [
            ['name' => 'بسته‌بندی', 'billing' => 'once', 'period_months' => 1, 'renewable' => false, 'category' => 'راه‌اندازی', 'default_selected' => true],
            ['name' => 'طراحی سایت', 'billing' => 'once', 'period_months' => 1, 'renewable' => false, 'category' => 'راه‌اندازی', 'default_selected' => true],
            ['name' => 'اینماد', 'billing' => 'custom', 'period_months' => 24, 'renewable' => true, 'category' => 'مجوز', 'default_selected' => true],
            ['name' => 'سرور', 'billing' => 'yearly', 'period_months' => 12, 'renewable' => true, 'category' => 'زیرساخت', 'default_selected' => true],
            ['name' => 'شارژ ترب', 'billing' => 'monthly', 'period_months' => 1, 'renewable' => true, 'category' => 'بازاریابی', 'default_selected' => true],
            ['name' => 'دامنه', 'billing' => 'yearly', 'period_months' => 12, 'renewable' => true, 'category' => 'زیرساخت', 'default_selected' => false],
            ['name' => 'SSL', 'billing' => 'yearly', 'period_months' => 12, 'renewable' => true, 'category' => 'زیرساخت', 'default_selected' => false],
            ['name' => 'درگاه پرداخت', 'billing' => 'once', 'period_months' => 1, 'renewable' => false, 'category' => 'راه‌اندازی', 'default_selected' => false],
            ['name' => 'پشتیبانی', 'billing' => 'monthly', 'period_months' => 1, 'renewable' => true, 'category' => 'عملیات', 'default_selected' => false],
        ];

        $out = [];
        $i = 0;
        foreach ($rows as $row) {
            ++$i;
            $out[] = [
                'id' => 'svc_'.$i,
                'name' => $row['name'],
                'billing' => $row['billing'],
                'period_months' => (int) $row['period_months'],
                'renewable' => ! empty($row['renewable']),
                'amount' => 0,
                'active' => true,
                'default_selected' => ! empty($row['default_selected']),
                'category' => $row['category'],
                'description' => '',
                'sort_order' => $i,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public static function get(): array
    {
        $row = SalesRahnSetting::query()->first();
        $raw = is_array($row?->payload) ? $row->payload : [];

        return self::mergeDefaults($raw);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function save(array $payload): array
    {
        $current = self::get();
        $next = self::sanitize(array_merge($current, $payload));

        $row = SalesRahnSetting::query()->first();
        if ($row) {
            $row->update(['payload' => $next]);
        } else {
            SalesRahnSetting::query()->create(['payload' => $next]);
        }

        return $next;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private static function mergeDefaults(array $raw): array
    {
        $defaults = self::defaults();
        $out = array_merge($defaults, $raw);

        if (empty($out['catalog']) || ! is_array($out['catalog'])) {
            $out['catalog'] = $defaults['catalog'];
        }
        if (empty($out['sales_definition']) || ! is_array($out['sales_definition'])) {
            $out['sales_definition'] = $defaults['sales_definition'];
        } else {
            $out['sales_definition'] = array_merge($defaults['sales_definition'], $out['sales_definition']);
        }
        if (empty($out['review']) || ! is_array($out['review'])) {
            $out['review'] = $defaults['review'];
        } else {
            $out['review'] = array_merge($defaults['review'], $out['review']);
        }

        return self::sanitize($out);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function sanitize(array $data): array
    {
        $defaults = self::defaults();

        $catalog = [];
        if (! empty($data['catalog']) && is_array($data['catalog'])) {
            $order = 0;
            foreach ($data['catalog'] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                ++$order;
                $id = preg_replace('/[^a-z0-9_\-]/i', '', (string) ($row['id'] ?? '')) ?: ('svc_'.bin2hex(random_bytes(4)));
                $billing = strtolower((string) ($row['billing'] ?? 'once'));
                if (! in_array($billing, ['once', 'monthly', 'yearly', 'custom'], true)) {
                    $billing = 'once';
                }
                $catalog[] = [
                    'id' => $id,
                    'name' => trim((string) ($row['name'] ?? '')),
                    'billing' => $billing,
                    'period_months' => max(1, (int) ($row['period_months'] ?? 1)),
                    'renewable' => ! empty($row['renewable']),
                    'amount' => max(0.0, (float) ($row['amount'] ?? 0)),
                    'active' => ! isset($row['active']) || ! empty($row['active']),
                    'default_selected' => ! empty($row['default_selected']),
                    'category' => trim((string) ($row['category'] ?? '')),
                    'description' => trim((string) ($row['description'] ?? '')),
                    'sort_order' => isset($row['sort_order']) ? (int) $row['sort_order'] : $order,
                ];
            }
            usort($catalog, static fn ($a, $b) => (int) $a['sort_order'] <=> (int) $b['sort_order']);
        }

        $salesDef = $defaults['sales_definition'];
        if (! empty($data['sales_definition']) && is_array($data['sales_definition'])) {
            foreach (['G', 'R', 'D', 'X'] as $key) {
                if (empty($data['sales_definition'][$key]) || ! is_array($data['sales_definition'][$key])) {
                    continue;
                }
                $salesDef[$key] = [
                    'enabled' => ! empty($data['sales_definition'][$key]['enabled']),
                    'label' => trim((string) ($data['sales_definition'][$key]['label'] ?? $salesDef[$key]['label'])),
                ];
            }
        }

        $review = $defaults['review'];
        if (! empty($data['review']) && is_array($data['review'])) {
            $review = [
                'enabled' => ! empty($data['review']['enabled']),
                'deviation_percent' => max(0.0, (float) ($data['review']['deviation_percent'] ?? 25)),
                'consecutive_months' => max(1, (int) ($data['review']['consecutive_months'] ?? 3)),
            ];
        }

        $clause = isset($data['clause_template'])
            ? trim((string) $data['clause_template'])
            : $defaults['clause_template'];
        if ($clause === '') {
            $clause = $defaults['clause_template'];
        }

        $pMin = max(0.0, min(1.0, (float) ($data['p_min'] ?? $defaults['p_min'])));
        $pMax = max($pMin, min(1.0, (float) ($data['p_max'] ?? $defaults['p_max'])));
        $pDef = max($pMin, min($pMax, (float) ($data['p_default'] ?? $defaults['p_default'])));

        return [
            'T' => max(1, (int) ($data['T'] ?? $defaults['T'])),
            'm' => max(0.0, (float) ($data['m'] ?? $defaults['m'])),
            'k' => max(0.0, min(1.0, (float) ($data['k'] ?? $defaults['k']))),
            'p_min' => $pMin,
            'p_max' => $pMax,
            'p_default' => $pDef,
            's_hat_default' => max(0.0, (float) ($data['s_hat_default'] ?? $defaults['s_hat_default'])),
            'clause_template' => $clause,
            'review' => $review,
            'sales_definition' => $salesDef,
            'catalog' => $catalog ?: $defaults['catalog'],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function catalogMap(): array
    {
        $settings = self::get();
        $map = [];
        foreach ((array) $settings['catalog'] as $row) {
            if (empty($row['active'])) {
                continue;
            }
            $map[(string) $row['id']] = $row;
        }

        return $map;
    }

    /**
     * @param  array<int, string>  $selectedIds
     * @param  array<int, array<string, mixed>>|null  $override
     * @return array<int, array<string, mixed>>
     */
    public static function resolveItems(array $selectedIds, ?array $override = null): array
    {
        $map = [];
        if (is_array($override)) {
            foreach ($override as $row) {
                if (is_array($row) && ! empty($row['id'])) {
                    $map[(string) $row['id']] = $row;
                }
            }
        } else {
            $map = self::catalogMap();
            foreach ((array) self::get()['catalog'] as $row) {
                $map[(string) $row['id']] = $row;
            }
        }

        $items = [];
        foreach ($selectedIds as $id) {
            $id = (string) $id;
            if (isset($map[$id])) {
                $items[] = $map[$id];
            }
        }

        return $items;
    }
}

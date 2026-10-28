<?php

namespace Modules\Crm\Http\Controllers\Customer360;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Services\CoreLicenseResolver;
use Modules\Crm\Entities\CrmBasalamOrder;
use Modules\Crm\Services\Hub\CustomerResolver;
use Modules\Marketplace\Entities\MarketplaceBasalamConnection;

/**
 * Site-push ingest of Basalam vendor orders (HMAC via webino.site; domain = host of site_url).
 * Upserts by (site_domain, external_id) so the plugin can resend safely.
 */
class BasalamOrderIngestController extends Controller
{
    public function store(Request $r): JsonResponse
    {
        $data = $r->validate([
            'site_url' => 'required|url|max:500',
            'orders' => 'required|array|min:1|max:200',
            // invoice_id = Basalam invoice id (what the plugin keys on: order meta _basalam_invoice_id). `id` is the legacy alias.
            'orders.*.id' => 'required_without:orders.*.invoice_id|nullable|max:100',
            'orders.*.invoice_id' => 'required_without:orders.*.id|nullable|max:100',
            'orders.*.fee' => 'nullable|numeric|min:0',
            'orders.*.status' => 'nullable|max:40',
            'orders.*.status_id' => 'nullable|integer',
            'orders.*.currency' => 'nullable|in:IRR,IRT',
            'orders.*.total' => 'nullable|numeric|min:0',
            'orders.*.buyer_name' => 'nullable|string|max:191',
            'orders.*.buyer_mobile' => 'nullable|string|max:30',
            'orders.*.items' => 'nullable|array|max:200',
            'orders.*.ordered_at' => 'nullable|date',
            'orders.*.paid_at' => 'nullable|date',
        ]);
        $domain = CoreLicenseResolver::normalizeDomain((string) parse_url($data['site_url'], PHP_URL_HOST));
        // Rollout flag: once every site has a secret, require a valid Site-Auth signature here even in grace mode.
        if ((bool) config('integrations.basalam.require_signed', false) && $r->attributes->get('webino_site_domain') === null) {
            return response()->json(['ok' => false, 'unavailable' => true, 'code' => 'signature_required', 'message' => __('messages.site_auth_failed')], 401);
        }
        // The middleware authenticates `domain` (or the site_url host); site_url must be that same site.
        $authed = CoreLicenseResolver::normalizeDomain((string) ($r->attributes->get('webino_site_domain') ?? $r->input('domain', '')));
        if ($domain === '' || ($authed !== '' && $authed !== $domain)) {
            return response()->json(['ok' => false, 'code' => 'domain_mismatch', 'message' => __('messages.site_auth_failed')], 422);
        }
        // Exact host match (a LIKE match would let shop.ir pick up myshop.ir's store).
        $conn = MarketplaceBasalamConnection::query()->orderByDesc('id')->get()
            ->first(fn ($c) => CoreLicenseResolver::normalizeDomain((string) parse_url((string) $c->site_url, PHP_URL_HOST)) === $domain);
        $account = ($conn && Schema::hasColumn('marketplace_basalam_connections', 'crm_account_id') ? $conn->crm_account_id : null)
            ?? CustomerResolver::byDomain($domain);

        $saved = 0;
        $hasFee = Schema::hasColumn('crm_basalam_orders', 'fee');
        foreach ($data['orders'] as $o) {
            $o['status'] = self::normalizeStatus($o['status_id'] ?? $o['status'] ?? null);
            $irt = ($o['currency'] ?? 'IRR') === 'IRT';
            foreach (['total', 'fee'] as $money) {
                if (isset($o[$money]) && $irt) {
                    $o[$money] = (float) $o[$money] * 10; // stored in IRR
                }
            }
            $externalId = (string) ($o['invoice_id'] ?? $o['id'] ?? '');
            if ($externalId === '') {
                continue;
            }
            $row = CrmBasalamOrder::query()->firstOrNew(['site_domain' => $domain, 'external_id' => $externalId]);
            if ($hasFee && array_key_exists('fee', $o) && $o['fee'] !== null) {
                $row->fee = $o['fee'];
            }
            $row->fill([
                'connection_id' => $conn?->id, 'crm_account_id' => $row->crm_account_id ?: $account,
                'status' => $o['status'] ?? $row->status, 'total' => $o['total'] ?? $row->total ?? 0,
                'buyer_name' => $o['buyer_name'] ?? $row->buyer_name, 'buyer_mobile' => $o['buyer_mobile'] ?? $row->buyer_mobile,
                'items' => $o['items'] ?? $row->items, 'items_count' => count($o['items'] ?? $row->items ?? []),
                'ordered_at' => $o['ordered_at'] ?? $row->ordered_at, 'paid_at' => $o['paid_at'] ?? $row->paid_at,
            ]);
            if ($row->isDirty()) {
                $row->save();
                $saved++;
            }
        }

        return response()->json(['ok' => true, 'saved' => $saved, 'linked' => (bool) $account]);
    }

    /** Basalam order status ids -> ERP canonical status (unknown ids kept as "basalam_{id}"). */
    public const STATUS_MAP = [
        3739 => 'wait_vendor', 3237 => 'preparation', 3238 => 'shipping', 3195 => 'completed',
        3067 => 'rejected', 3233 => 'canceled',
    ];

    /**
     * WooCommerce / plugin status slugs (woocommerce_statuses mode) -> ERP canonical status.
     * Plugin custom slugs: bslm-wait-vendor, bslm-preparation, bslm-shipping, bslm-completed, bslm-rejected (+canceled).
     */
    public const TEXT_STATUS_MAP = [
        'wait-vendor' => 'wait_vendor', 'pending' => 'wait_vendor', 'on-hold' => 'wait_vendor',
        'preparation' => 'preparation', 'processing' => 'preparation',
        'shipping' => 'shipping', 'shipped' => 'shipping',
        'completed' => 'completed', 'delivered' => 'completed',
        'rejected' => 'rejected', 'failed' => 'rejected',
        'canceled' => 'canceled', 'cancelled' => 'canceled', 'refunded' => 'canceled',
    ];

    public static function normalizeStatus(mixed $s): ?string
    {
        if ($s === null || $s === '') {
            return null;
        }
        if (is_numeric($s)) {
            return self::STATUS_MAP[(int) $s] ?? 'basalam_'.(int) $s;
        }
        $key = strtolower(trim((string) $s));
        $key = preg_replace('/^(wc-|bslm-)/', '', $key);
        $key = str_replace('_', '-', (string) $key);

        return self::TEXT_STATUS_MAP[$key] ?? mb_substr((string) $s, 0, 40);
    }
}

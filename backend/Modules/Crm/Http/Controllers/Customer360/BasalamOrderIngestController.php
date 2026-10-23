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
            'orders.*.id' => 'required|max:100',
            'orders.*.status' => 'nullable|string|max:40',
            'orders.*.total' => 'nullable|numeric|min:0',
            'orders.*.buyer_name' => 'nullable|string|max:191',
            'orders.*.buyer_mobile' => 'nullable|string|max:30',
            'orders.*.items' => 'nullable|array|max:200',
            'orders.*.ordered_at' => 'nullable|date',
            'orders.*.paid_at' => 'nullable|date',
        ]);
        $domain = CoreLicenseResolver::normalizeDomain((string) parse_url($data['site_url'], PHP_URL_HOST));
        $conn = MarketplaceBasalamConnection::query()->where('site_url', 'like', '%'.$domain.'%')->orderByDesc('id')->first();
        $account = ($conn && Schema::hasColumn('marketplace_basalam_connections', 'crm_account_id') ? $conn->crm_account_id : null)
            ?? CustomerResolver::byDomain($domain);

        $saved = 0;
        foreach ($data['orders'] as $o) {
            $row = CrmBasalamOrder::query()->firstOrNew(['site_domain' => $domain, 'external_id' => (string) $o['id']]);
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
}

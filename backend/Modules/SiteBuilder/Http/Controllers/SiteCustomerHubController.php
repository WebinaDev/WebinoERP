<?php

namespace Modules\SiteBuilder\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Support\MutationAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Crm\Services\Hub\CustomerHubAccess;
use Modules\Crm\Services\Hub\SiteCustomerSummary;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;

/** "Customer hub" card on the site control panel (owner Customer 360 summary + link/unlink). */
class SiteCustomerHubController extends Controller
{
    public function show(Request $r, WebinoSiteProvision $siteProvision, SiteCustomerSummary $summary): JsonResponse
    {
        return response()->json(['data' => $summary->build($siteProvision, $r->user())]);
    }

    public function link(Request $r, WebinoSiteProvision $siteProvision, CustomerHubAccess $access, SiteCustomerSummary $summary): JsonResponse
    {
        abort_unless($access->canManage($r->user(), 'profile'), 403, __('Forbidden'));
        $data = $r->validate(['crm_account_id' => ['nullable', 'integer', 'exists:crm_accounts,id']]);
        $old = $siteProvision->crm_account_id;
        $siteProvision->crm_account_id = $data['crm_account_id'] ?? null;
        $siteProvision->save();
        MutationAudit::record($r->user()?->id, 'site_builder', 'provision.customer_link', WebinoSiteProvision::class, $siteProvision->id,
            ['crm_account_id' => ['from' => $old, 'to' => $siteProvision->crm_account_id]]);

        return response()->json(['data' => $summary->build($siteProvision->fresh(), $r->user())]);
    }
}

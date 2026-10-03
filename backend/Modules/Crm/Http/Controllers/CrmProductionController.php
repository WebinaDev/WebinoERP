<?php

namespace Modules\Crm\Http\Controllers;

use App\Support\MutationAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Crm\Entities\CrmCampaignSpend;
use Modules\Crm\Entities\CrmDiscountNode;
use Modules\Crm\Entities\CrmProductRule;
use Modules\Crm\Entities\CrmQuoteApproval;
use Modules\Crm\Entities\ContentItem;
use Modules\Crm\Services\AttributionService;
use Modules\Crm\Services\SocialPublishService;
use Modules\Integrations\Entities\SocialConnector;

class CrmProductionController extends Controller
{
    public function rules(): JsonResponse
    {
        return response()->json(['data' => CrmProductRule::query()->orderBy('id')->get()]);
    }

    public function storeRule(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => 'required|integer',
            'related_product_id' => 'required|integer|different:product_id',
            'kind' => 'required|in:requires,excludes',
            'min_qty' => 'nullable|numeric|min:0.01',
        ]);
        $rule = CrmProductRule::query()->create([
            'product_id' => $data['product_id'],
            'related_product_id' => $data['related_product_id'],
            'kind' => $data['kind'],
            'min_qty' => $data['min_qty'] ?? 1,
        ]);

        return response()->json(['data' => $rule], 201);
    }

    public function discounts(): JsonResponse
    {
        return response()->json(['data' => CrmDiscountNode::query()->orderBy('id')->get()]);
    }

    public function storeDiscount(Request $request): JsonResponse
    {
        $data = $request->validate([
            'parent_id' => 'nullable|integer',
            'scope' => 'required|in:product,category,book,deal',
            'scope_key' => 'required|string|max:80',
            'name' => 'required|string|max:120',
            'percent' => 'required|numeric|min:0|max:100',
            'min_amount' => 'nullable|numeric|min:0',
            'stackable' => 'nullable|boolean',
        ]);
        $node = CrmDiscountNode::query()->create($data);

        return response()->json(['data' => $node], 201);
    }

    public function approvals(): JsonResponse
    {
        return response()->json(['data' => CrmQuoteApproval::query()->orderByDesc('id')->limit(50)->get()]);
    }

    public function decideApproval(Request $request, int $id): JsonResponse
    {
        $row = CrmQuoteApproval::query()->findOrFail($id);
        $data = $request->validate([
            'status' => 'required|in:approved,rejected',
            'reason' => 'nullable|string|max:200',
        ]);
        $row->update([
            'status' => $data['status'],
            'reason' => $data['reason'] ?? null,
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
        ]);
        MutationAudit::record($request->user()->id, 'crm', 'quote.'.$data['status'], 'quote_approval', $row->id, $data);

        return response()->json(['data' => $row->fresh()]);
    }

    public function touchpoints(Request $request, AttributionService $attribution): JsonResponse
    {
        $data = $request->validate([
            'lead_id' => 'nullable|integer',
            'deal_id' => 'nullable|integer',
            'utm_source' => 'nullable|string|max:120',
            'utm_medium' => 'nullable|string|max:120',
            'utm_campaign' => 'nullable|string|max:120',
            'utm_content' => 'nullable|string|max:120',
            'utm_term' => 'nullable|string|max:120',
            'channel' => 'nullable|string|max:32',
            'occurred_at' => 'nullable|date',
        ]);
        $touch = $attribution->record($data);

        return response()->json(['data' => $touch], 201);
    }

    public function storeSpend(Request $request): JsonResponse
    {
        $data = $request->validate([
            'campaign_key' => 'required|string|max:120',
            'name' => 'nullable|string|max:160',
            'spent' => 'required|numeric|min:0',
            'currency_code' => 'nullable|string|size:3',
            'starts_on' => 'nullable|date',
            'ends_on' => 'nullable|date',
        ]);
        $row = CrmCampaignSpend::query()->create($data);

        return response()->json(['data' => $row], 201);
    }

    public function roi(Request $request, AttributionService $attribution): JsonResponse
    {
        $model = (string) $request->query('model', 'linear');

        return response()->json(['data' => $attribution->roi($model)]);
    }

    public function connectors(): JsonResponse
    {
        return response()->json(['data' => SocialConnector::query()->orderByDesc('id')->get()->map(fn (SocialConnector $row) => [
            'id' => $row->id,
            'network' => $row->network,
            'name' => $row->name,
            'external_id' => $row->external_id,
            'enabled' => $row->enabled,
            'status' => $row->status,
            'has_token' => filled($row->access_token),
        ])]);
    }

    public function storeConnector(Request $request): JsonResponse
    {
        $data = $request->validate([
            'network' => 'required|in:instagram,linkedin',
            'name' => 'required|string|max:120',
            'access_token' => 'required|string',
            'external_id' => 'required|string|max:160',
            'enabled' => 'nullable|boolean',
        ]);
        $row = SocialConnector::query()->create($data);

        return response()->json(['data' => ['id' => $row->id, 'network' => $row->network, 'name' => $row->name]], 201);
    }

    public function publishItem(int $id, SocialPublishService $publisher): JsonResponse
    {
        $item = ContentItem::query()->findOrFail($id);
        $fresh = $publisher->publish($item);
        MutationAudit::record(request()->user()?->id, 'crm', 'content.publish', 'content_item', $fresh->id, [
            'publish_status' => $fresh->publish_status,
            'external_post_id' => $fresh->external_post_id,
        ]);

        return response()->json(['data' => $fresh], $fresh->publish_status === 'published' ? 200 : 422);
    }
}

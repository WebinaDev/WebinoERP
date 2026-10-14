<?php

namespace Modules\Sales\Http\Controllers\Storefront;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Sales\Entities\SalesCoupon;
use Modules\Sales\Entities\SalesProductQuestion;
use Modules\Sales\Entities\SalesProductReview;
use Modules\Sales\Entities\SalesStorefrontOrder;
use Modules\Sales\Http\Controllers\Controller;

/** Staff side of the online shop: orders, review/question moderation, coupons. */
class StorefrontAdminController extends Controller
{
    public function orders(Request $request): JsonResponse
    {
        $rows = SalesStorefrontOrder::query()->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))->orderByDesc('id')->paginate(30);
        $rows->getCollection()->transform(fn ($o) => AccountController::orderPayload($o));

        return response()->json($rows);
    }

    public function updateOrder(Request $request, int $order): JsonResponse
    {
        $data = $request->validate(['status' => 'sometimes|in:pending_payment,processing,shipped,delivered,cancelled', 'tracking_code' => 'nullable|string|max:60']);
        $row = SalesStorefrontOrder::query()->findOrFail($order);
        $row->update($data);

        return response()->json(['data' => AccountController::orderPayload($row)]);
    }

    public function reviews(Request $request): JsonResponse
    {
        return response()->json(SalesProductReview::query()->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))->orderByDesc('id')->paginate(30));
    }

    public function updateReview(Request $request, int $review): JsonResponse
    {
        $data = $request->validate(['status' => 'required|in:pending,approved,rejected']);
        $row = SalesProductReview::query()->findOrFail($review);
        $row->update($data + ($data['status'] === 'approved' ? ['reports' => 0] : []));

        return response()->json(['data' => $row]);
    }

    public function questions(Request $request): JsonResponse
    {
        return response()->json(SalesProductQuestion::query()->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))->orderByDesc('id')->paginate(30));
    }

    public function answerQuestion(Request $request, int $question): JsonResponse
    {
        $data = $request->validate(['answer' => 'nullable|string|max:2000', 'status' => 'required|in:pending,answered,rejected']);
        $row = SalesProductQuestion::query()->findOrFail($question);
        $row->update(['answer' => isset($data['answer']) ? strip_tags($data['answer']) : $row->answer, 'status' => $data['status']]);

        return response()->json(['data' => $row]);
    }

    public function coupons(): JsonResponse
    {
        return response()->json(['data' => SalesCoupon::query()->orderByDesc('id')->get()]);
    }

    public function saveCoupon(Request $request, ?int $coupon = null): JsonResponse
    {
        $data = $request->validate([
            'code' => 'required|string|max:40|regex:/^[A-Za-z0-9_\-]+$/',
            'type' => 'required|in:percent,fixed',
            'value' => 'required|numeric|min:0',
            'min_total' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date',
            'active' => 'nullable|boolean',
        ]);
        $data['code'] = mb_strtoupper($data['code']);
        abort_if($data['type'] === 'percent' && $data['value'] > 100, 422, 'درصد تخفیف حداکثر ۱۰۰ است.');
        abort_if(SalesCoupon::query()->where('code', $data['code'])->when($coupon, fn ($q) => $q->whereKeyNot($coupon))->exists(), 422, 'این کد قبلاً ثبت شده است.');
        $row = $coupon ? tap(SalesCoupon::query()->findOrFail($coupon))->update($data) : SalesCoupon::query()->create($data);

        return response()->json(['data' => $row], $coupon ? 200 : 201);
    }

    public function destroyCoupon(int $coupon): JsonResponse
    {
        SalesCoupon::query()->findOrFail($coupon)->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }
}

<?php

namespace Modules\Sales\Http\Controllers\Storefront;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Sales\Entities\SalesInvoice;
use Modules\Sales\Entities\SalesProductFavorite;
use Modules\Sales\Entities\SalesProductQuestion;
use Modules\Sales\Entities\SalesStorefrontOrder;
use Modules\Sales\Http\Controllers\Controller;
use Modules\Sales\Services\Storefront\StorefrontCatalog;

/** Customer account: orders, favorites, product questions. */
class AccountController extends Controller
{
    public function __construct(private StorefrontCatalog $catalog) {}

    public function orders(Request $request): JsonResponse
    {
        $rows = SalesStorefrontOrder::query()->where('user_id', $request->user()->id)->orderByDesc('id')->limit(100)->get();

        return response()->json(['data' => $rows->map(fn ($o) => self::orderPayload($o))->values()]);
    }

    /** Courses the customer bought (paid orders with course items). */
    public function courses(Request $request): JsonResponse
    {
        $slugs = [];
        $orders = SalesStorefrontOrder::query()->where('user_id', $request->user()->id)->whereIn('status', ['paid', 'processing', 'shipped', 'delivered'])->orderByDesc('id')->get();
        foreach ($orders as $order) {
            foreach ((array) $order->items as $item) {
                if (($item['kind'] ?? '') === 'course' && ! empty($item['course_slug'])) {
                    $slugs[$item['course_slug']] ??= ['order_number' => $order->number, 'purchased_at' => ($order->paid_at ?? $order->created_at)?->toIso8601String()];
                }
            }
        }
        $courses = $slugs === [] ? collect() : \Modules\Marketing\Entities\MarketingAcademyCourse::query()->whereIn('slug', array_keys($slugs))->get();

        return response()->json(['data' => $courses->map(fn ($c) => [
            'id' => $c->id, 'slug' => $c->slug, 'title' => $c->title, 'cover_url' => $c->cover_url, 'level' => $c->level,
            'duration' => $c->duration, 'instructor' => $c->instructor, 'url' => '/academy/'.$c->slug,
            'lessons_count' => $c->lessons()->count(),
        ] + $slugs[$c->slug])->values()]);
    }

    public function profile(Request $request): JsonResponse
    {
        $u = $request->user();
        $orders = SalesStorefrontOrder::query()->where('user_id', $u->id);

        return response()->json(['data' => [
            'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'mobile' => $u->mobile ?? $u->phone ?? null,
            'orders_count' => (clone $orders)->count(),
            'paid_total_minor' => (int) (clone $orders)->whereIn('status', ['paid', 'processing', 'shipped', 'delivered'])->sum('total'),
            'is_staff' => method_exists($u, 'hasAnyRole') ? $u->hasAnyRole(['admin', 'super-admin', 'super_admin', 'staff', 'team_member', 'manager']) : false,
        ]]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $u = $request->user();
        $data = $request->validate(['name' => 'required|string|max:191', 'current_password' => 'nullable|string', 'password' => 'nullable|string|min:8|confirmed']);
        $u->name = strip_tags($data['name']);
        if (! empty($data['password'])) {
            abort_unless(\Illuminate\Support\Facades\Hash::check((string) ($data['current_password'] ?? ''), (string) $u->password), 422, 'رمز عبور فعلی درست نیست.');
            $u->password = \Illuminate\Support\Facades\Hash::make($data['password']);
        }
        $u->save();

        return $this->profile($request);
    }

    public function favorites(Request $request): JsonResponse
    {
        $rows = SalesProductFavorite::query()->with('product')->where('user_id', $request->user()->id)->orderByDesc('id')->get();

        return response()->json(['data' => $rows->filter(fn ($f) => $f->product)->map(fn ($f) => $this->catalog->serialize($f->product))->values()]);
    }

    public function addFavorite(Request $request, int $product): JsonResponse
    {
        (clone $this->catalog->query())->findOrFail($product);
        SalesProductFavorite::query()->firstOrCreate(['user_id' => $request->user()->id, 'product_id' => $product]);

        return response()->json(['data' => ['favorited' => true]]);
    }

    public function removeFavorite(Request $request, int $product): JsonResponse
    {
        SalesProductFavorite::query()->where('user_id', $request->user()->id)->where('product_id', $product)->delete();

        return response()->json(['data' => ['favorited' => false]]);
    }

    public function questions(Request $request): JsonResponse
    {
        $rows = SalesProductQuestion::query()->where('user_id', $request->user()->id)->orderByDesc('id')->limit(100)->get();

        return response()->json(['data' => $rows->map(fn ($q) => ['id' => $q->id, 'product_id' => $q->product_id, 'body' => $q->body, 'answer' => $q->answer, 'status' => $q->status, 'created_at' => $q->created_at?->toIso8601String()])->values()]);
    }

    public function askQuestion(Request $request): JsonResponse
    {
        $data = $request->validate(['product_id' => 'required|integer', 'body' => 'required|string|min:3|max:1000']);
        (clone $this->catalog->query())->findOrFail($data['product_id']);
        $row = SalesProductQuestion::query()->create(['product_id' => $data['product_id'], 'user_id' => $request->user()->id, 'body' => strip_tags($data['body']), 'status' => 'pending']);

        return response()->json(['data' => ['id' => $row->id, 'status' => $row->status]], 201);
    }

    /** @return array<string, mixed> */
    public static function orderPayload(SalesStorefrontOrder $order): array
    {
        // A paid invoice (Integrations payment side effect) moves the order forward.
        if ($order->status === 'pending_payment' && $order->invoice_id && (string) SalesInvoice::query()->whereKey($order->invoice_id)->value('status') === 'paid') {
            $order->update(['status' => 'processing']);
        }

        return [
            'id' => $order->id,
            'number' => $order->number,
            'status' => $order->status,
            'payment_method' => $order->payment_method,
            'items' => $order->items,
            'address' => $order->address,
            'subtotal_minor' => (int) $order->subtotal,
            'discount_minor' => (int) $order->discount,
            'shipping_minor' => (int) $order->shipping,
            'total_minor' => (int) $order->total,
            'tracking_code' => $order->tracking_code,
            'customer_name' => $order->customer_name,
            'customer_email' => $order->customer_email,
            'phone' => $order->phone,
            'payment_mode' => $order->payment_mode,
            'paid_at' => $order->paid_at?->toIso8601String(),
            'is_guest' => $order->user_id === null,
            'invoice_id' => $order->invoice_id,
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }
}

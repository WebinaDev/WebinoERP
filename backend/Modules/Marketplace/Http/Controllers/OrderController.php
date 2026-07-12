<?php

namespace Modules\Marketplace\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Marketplace\Entities\MarketplaceOrder;

class OrderController extends Controller
{
    use PaginatesApi;

    public function index(Request $request): JsonResponse
    {
        $query = MarketplaceOrder::query()->orderByDesc('created_at');
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return $this->paginatedResponse($query->paginate($this->perPage($request)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_number' => 'nullable|string|max:50|unique:marketplace_orders,order_number',
            'total' => 'nullable|numeric|min:0',
            'status' => 'nullable|string|in:pending,paid,cancelled,fulfilled',
            'user_id' => 'nullable|exists:users,id',
        ]);

        if (empty($data['order_number'])) {
            $data['order_number'] = 'ORD-'.now()->format('Ymd').'-'.str_pad((string) (MarketplaceOrder::query()->count() + 1), 4, '0', STR_PAD_LEFT);
        }
        $data['status'] = $data['status'] ?? 'pending';
        $data['total'] = $data['total'] ?? 0;
        if (! isset($data['user_id'])) {
            $data['user_id'] = $request->user()?->id;
        }

        $order = MarketplaceOrder::create($data);

        return response()->json(['data' => $order, 'message' => 'Created'], 201);
    }

    public function show(MarketplaceOrder $order): JsonResponse
    {
        return response()->json(['data' => $order]);
    }

    public function update(Request $request, MarketplaceOrder $order): JsonResponse
    {
        $data = $request->validate([
            'order_number' => 'sometimes|string|max:50|unique:marketplace_orders,order_number,'.$order->id,
            'total' => 'nullable|numeric|min:0',
            'status' => 'sometimes|string|in:pending,paid,cancelled,fulfilled',
            'user_id' => 'nullable|exists:users,id',
        ]);
        $order->update($data);

        return response()->json(['data' => $order->fresh(), 'message' => 'Updated']);
    }

    public function destroy(MarketplaceOrder $order): JsonResponse
    {
        $order->delete();

        return response()->noContent();
    }
}

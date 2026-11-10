<?php

namespace Modules\Sales\Services\Storefront;

use Modules\Sales\Entities\SalesInvoice;
use Modules\Sales\Entities\SalesStorefrontOrder;
use Modules\Sales\Http\Controllers\Storefront\CheckoutController;

/**
 * Abandoned checkouts: a storefront order still waiting for payment after `pending_order_ttl_hours`
 * is cancelled so its gift card hold, stock and coupon use are released (scheduled hourly).
 */
class StorefrontPendingOrders
{
    public function __construct(private StorefrontSettings $settings) {}

    /** @return int number of orders expired */
    public function expire(?int $hours = null): int
    {
        $hours ??= (int) $this->settings->get('pending_order_ttl_hours');
        if ($hours <= 0) {
            return 0;
        }
        $count = 0;
        SalesStorefrontOrder::query()
            ->where('status', 'pending_payment')
            ->where('created_at', '<', now()->subHours($hours))
            ->orderBy('id')
            ->chunkById(100, function ($orders) use (&$count) {
                foreach ($orders as $order) {
                    $invoice = $order->invoice_id ? SalesInvoice::query()->find($order->invoice_id) : null;
                    // A late callback may already have paid the invoice; leave those for the normal flow.
                    if ($invoice && $invoice->status === 'paid') {
                        continue;
                    }
                    $meta = (array) ($order->meta ?? []);
                    $meta['expired_at'] = now()->toIso8601String();
                    $meta['cancel_reason'] = 'payment_timeout';
                    $order->forceFill(['status' => 'cancelled', 'meta' => $meta])->save();
                    if ($invoice && $invoice->status !== 'cancelled') {
                        $invoice->forceFill(['status' => 'cancelled'])->save();
                    }
                    CheckoutController::onStatusChange($order->fresh(), 'pending_payment', 'cancelled');
                    $count++;
                }
            });

        return $count;
    }
}

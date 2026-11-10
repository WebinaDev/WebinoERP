<?php

namespace Modules\Sales\Http\Controllers\Storefront;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Integrations\Services\Payments\GatewayConfigStore;
use Modules\Sales\Http\Controllers\Controller;
use Modules\Sales\Services\Storefront\CheckoutFields;
use Modules\Sales\Services\Storefront\CheckoutFlowSettings;
use Modules\Sales\Services\Storefront\StorefrontCatalog;
use Modules\Sales\Services\Storefront\StorefrontSettings;

/**
 * Stepped cart/checkout (signed-in or guest via X-Cart-Token): config, per-step validate + save
 * on the cart, totals after every step. Orders are still placed with POST /checkout, which falls
 * back to the saved step values.
 */
class CheckoutStepsController extends Controller
{
    private const STEP_KEYS = [
        'address' => ['shipping_address'],
        'delivery_time' => [],
        'shipping' => [],
        'payment' => ['payment_mode'],
    ];

    public function __construct(
        private CheckoutFlowSettings $flow,
        private StorefrontCatalog $catalog,
        private CartController $carts,
    ) {}

    public function adminShow(): JsonResponse
    {
        return response()->json(['data' => ['settings' => $this->flow->get(), 'defaults' => CheckoutFlowSettings::defaults(), 'configured' => $this->flow->configured()]]);
    }

    public function adminUpdate(Request $request): JsonResponse
    {
        $data = $request->validate(['data' => 'required|array']);

        return response()->json(['data' => ['settings' => $this->flow->save($data['data']), 'configured' => true]]);
    }

    public function config(Request $request, GatewayConfigStore $configs): JsonResponse
    {
        $cfg = $this->flow->get();
        $settings = app(StorefrontSettings::class);
        $options = app(CheckoutController::class)->paymentOptions($request, $configs)->getData(true)['data'];
        $hidden = (array) $cfg['payment']['hidden_gateways'];
        $order = array_values((array) $cfg['payment']['gateway_order']);
        $rows = array_values(array_filter($options['gateways'], fn ($g) => ! in_array((string) $g['code'], $hidden, true)));
        if ($order !== []) {
            usort($rows, function ($a, $b) use ($order) {
                $ia = array_search((string) $a['code'], $order, true);
                $ib = array_search((string) $b['code'], $order, true);

                return ($ia === false ? PHP_INT_MAX : $ia) <=> ($ib === false ? PHP_INT_MAX : $ib);
            });
        }

        return response()->json(['data' => CheckoutFlowSettings::publicPayload($cfg) + [
            'gateways' => $rows,
            'payment_modes' => $options['modes'],
            'shipping_methods' => array_map(fn ($m) => ['id' => $m['id'], 'title' => $m['title'], 'cost_minor' => (int) $m['cost_minor'], 'free_over_minor' => (int) $m['free_over_minor'], 'delivery_time_required' => CheckoutFlowSettings::deliveryTimeApplies($cfg, ['id' => $m['id']])], $settings->shippingMethods()),
            'pricing' => ['single_price' => ! $settings->get('pricing_enabled')],
            'gift_cards_enabled' => (bool) $settings->get('gift_cards_enabled'),
            'require_login' => (bool) $settings->get('require_login'),
        ]]);
    }

    public function session(Request $request): JsonResponse
    {
        $cart = $this->carts->cartFor($request, false);
        $values = $cart->exists ? (array) ($cart->meta['checkout'] ?? []) : [];
        $lines = $cart->exists ? $this->carts->activeLines($cart) : [];

        return response()->json(['data' => [
            'token' => $cart->user_id ? null : $cart->token,
            'values' => $values,
            'completed' => $cart->exists ? array_values((array) ($cart->meta['checkout_steps'] ?? [])) : [],
            'totals' => $lines === [] ? null : $this->totals($lines, $values, $cart->coupon_code),
        ]]);
    }

    public function step(Request $request, string $step): JsonResponse
    {
        abort_unless(in_array($step, CheckoutFlowSettings::STEPS, true), 404);
        $cart = $this->carts->cartFor($request);
        $lines = $this->carts->activeLines($cart);
        if ($lines === []) {
            return response()->json(['message' => 'سبد خرید خالی است.'], 422);
        }
        $cfg = $this->flow->get();
        $values = array_merge((array) ($cart->meta['checkout'] ?? []), $this->stepInput($request, $cfg, $step));
        $totals = $this->totals($lines, $values, $cart->coupon_code);
        // Only a method the buyer picked scopes the delivery-time step (the quote falls back to the first method).
        $rate = $totals['requires_shipping'] && ! empty($values['shipping_method']) && $totals['shipping_method'] ? ['id' => $totals['shipping_method']] : null;
        $errors = CheckoutFields::errors(CheckoutFields::forCart($cfg, (bool) $totals['requires_shipping']), $step, $values, $rate, true);
        if ($step === 'address' && isset($values['shipping_state_code']) && $values['shipping_state_code'] !== '' && ! GeoController::valid((string) $values['shipping_state_code'])) {
            $errors['shipping_state_code'] = 'استان نامعتبر است.';
        }
        if ($step === 'shipping' && $totals['shipping_method_error']) {
            $errors['shipping_method'] = $totals['shipping_method_error'];
        }
        if ($step === 'payment') {
            if (! empty($values['coupon_code']) && $totals['coupon_error']) {
                $errors['coupon_code'] = (string) $totals['coupon_error'];
            }
            if (! empty($values['gift_card_code']) && $totals['gift_card_error']) {
                $errors['gift_card_code'] = (string) $totals['gift_card_error'];
            }
        }
        if ($errors !== []) {
            return response()->json(['message' => reset($errors), 'errors' => array_map(fn ($m) => [$m], $errors)], 422);
        }
        $meta = (array) ($cart->meta ?? []);
        $meta['checkout'] = $values;
        $meta['checkout_steps'] = array_values(array_unique(array_merge((array) ($meta['checkout_steps'] ?? []), [$step])));
        $cart->forceFill(['meta' => $meta])->save();

        return response()->json(['data' => [
            'step' => $step,
            'valid' => true,
            'token' => $cart->user_id ? null : $cart->token,
            'values' => $values,
            'next_step' => $this->nextStep($cfg, $step, $rate, $totals['requires_shipping']),
            'totals' => $totals,
        ]]);
    }

    /** @return array<string, mixed> */
    private function totals(array $lines, array $values, ?string $cartCoupon): array
    {
        $cfg = $this->flow->get();
        $q = $this->catalog->quote($lines, ($values['coupon_code'] ?? null) ?: $cartCoupon, [
            'shipping_method' => $values['shipping_method'] ?? null,
            'gift_card_code' => $values['gift_card_code'] ?? null,
        ]);
        $q['shipping_methods'] = array_map(fn ($m) => $m + ['delivery_time_required' => CheckoutFlowSettings::deliveryTimeApplies($cfg, ['id' => $m['id']])], $q['shipping_methods']);
        $q['delivery_time_required'] = $q['requires_shipping'] && CheckoutFlowSettings::deliveryTimeApplies($cfg, $q['shipping_method'] ? ['id' => $q['shipping_method']] : null);

        return $q;
    }

    /** @return array<string, mixed> */
    private function stepInput(Request $request, array $cfg, string $step): array
    {
        $keys = array_merge(array_column($cfg['fields'][$step] ?? [], 'key'), self::STEP_KEYS[$step] ?? []);
        $keys = array_diff($keys, ['order_summary', 'terms']);
        if ($step === 'payment') {
            $keys[] = 'terms_accepted';
        }
        $out = [];
        foreach ($keys as $k) {
            if ($request->exists($k)) {
                $v = $request->input($k);
                if (is_string($v)) {
                    $v = mb_substr(trim(strip_tags($v)), 0, 500);
                } elseif (is_array($v)) {
                    $v = array_map(fn ($x) => is_scalar($x) ? mb_substr(strip_tags((string) $x), 0, 300) : null, array_slice($v, 0, 20, true));
                }
                $out[$k] = $v;
            }
        }
        if (isset($out['payment_mode']) && ! in_array($out['payment_mode'], ['cash', 'installment'], true)) {
            unset($out['payment_mode']);
        }

        return $out;
    }

    private function nextStep(array $cfg, string $step, ?array $rate, bool $physical): ?string
    {
        $steps = array_values(array_filter($cfg['steps'], fn ($s) => $s['enabled']));
        usort($steps, fn ($a, $b) => $a['order'] <=> $b['order']);
        $keys = array_column($steps, 'key');
        $i = array_search($step, $keys, true);
        for ($j = ($i === false ? -1 : $i) + 1; $j < count($keys); $j++) {
            // Digital-only carts skip shipping and delivery time; delivery time also skips when it does not apply to the picked method.
            if (! $physical && in_array($keys[$j], ['shipping', 'delivery_time'], true)) {
                continue;
            }
            if ($keys[$j] === 'delivery_time' && $rate !== null && ! CheckoutFlowSettings::deliveryTimeApplies($cfg, $rate)) {
                continue;
            }

            return $keys[$j];
        }

        return null;
    }
}

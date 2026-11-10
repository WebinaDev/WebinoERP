<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Core\Entities\SystemModule;
use Modules\Integrations\Entities\PaymentIntent;
use Modules\Integrations\Services\Payments\GatewayConfigStore;
use Modules\Integrations\Services\Payments\PaymentSideEffects;
use Modules\Sales\Entities\SalesCatalogItem;
use Modules\Sales\Entities\SalesCoupon;
use Modules\Sales\Entities\SalesGiftCard;
use Modules\Sales\Entities\SalesInvoice;
use Modules\Sales\Entities\SalesStorefrontOrder;
use Modules\Sales\Services\Storefront\StorefrontGiftCards;
use Modules\Sales\Services\Storefront\StorefrontSettings;
use Tests\TestCase;

/**
 * Shop purchase flow end to end: variants through cart and order, stock, gift cards, pricing
 * module off, stepped checkout (guest via X-Cart-Token), cancel side effects, payment callback.
 */
class StorefrontPurchaseFlowTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->token = str_repeat('a', 40);
    }

    private function product(array $meta = [], float $price = 1000000): SalesCatalogItem
    {
        return SalesCatalogItem::query()->create(['name' => 'تی‌شرت', 'sku' => 'SKU-'.uniqid(), 'price' => $price, 'status' => 'active', 'type' => 'product', 'meta' => $meta + ['storefront' => true, 'slug' => 'p'.uniqid()]]);
    }

    private function guest(): array
    {
        return ['X-Cart-Token' => $this->token];
    }

    private function contact(array $extra = []): array
    {
        return $extra + ['customer_name' => 'سارا', 'customer_phone' => '09121234567', 'shipping_address' => ['line' => 'تهران، خیابان یک'], 'shipping_state_code' => 'THR'];
    }

    private function admin(): User
    {
        SystemModule::query()->firstOrCreate(['slug' => 'sales'], ['name' => 'Sales', 'is_active' => true]);
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('system_manager');

        return $admin;
    }

    public function test_variants_flow_through_cart_order_stock_and_cancel(): void
    {
        $p = $this->product(['variants' => [
            ['id' => 'red', 'name' => 'قرمز', 'price' => 1200000, 'sale_price' => 900000, 'stock' => 2],
            ['id' => 'blue', 'name' => 'آبی', 'stock' => 5],
        ]]);
        $detail = $this->getJson('/api/v1/public/catalog/items/'.$p->meta['slug'])->assertOk()->json('data');
        $this->assertCount(2, $detail['variants']);
        $this->assertSame(900000, $detail['variants'][0]['discounted_price_minor']);
        $this->assertSame(1200000, $detail['variants'][0]['price_minor']);

        $this->postJson('/api/v1/cart/items', ['product_id' => $p->id], $this->guest())->assertStatus(422)->assertJsonValidationErrors('variant_id');
        $this->postJson('/api/v1/cart/items', ['product_id' => $p->id, 'variant_id' => 'nope'], $this->guest())->assertStatus(422);
        $this->postJson('/api/v1/cart/items', ['product_id' => $p->id, 'variant_id' => 'red', 'quantity' => 3], $this->guest())->assertStatus(422);
        $this->postJson('/api/v1/cart/items', ['product_id' => $p->id, 'variant_id' => 'red', 'quantity' => 2], $this->guest())->assertOk();
        $cart = $this->postJson('/api/v1/cart/items', ['product_id' => $p->id, 'variant_id' => 'blue'], $this->guest())->assertOk()->json('data');
        $this->assertCount(2, $cart['items']);
        $red = collect($cart['items'])->firstWhere('variant_id', 'red');
        $this->assertSame(900000, $red['unit_price_minor']);
        $this->assertSame(1200000, $red['regular_price_minor']);
        $this->assertTrue($red['on_sale']);
        $this->assertSame(1800000 + 1000000, $cart['pricing']['subtotal_minor']);
        $this->assertSame(600000, $cart['pricing']['sale_savings_minor']);
        $this->putJson('/api/v1/cart/lines/'.$red['id'], ['quantity' => 3], $this->guest())->assertStatus(422);

        SalesCoupon::query()->create(['code' => 'OFF', 'type' => 'fixed', 'value' => 100000, 'min_total' => 0]);
        $order = $this->postJson('/api/v1/checkout', $this->contact(['coupon_code' => 'OFF']), $this->guest())->assertCreated()->json('data');
        $this->assertSame('قرمز', collect($order['items'])->firstWhere('variant_id', 'red')['variant_name']);
        $this->assertSame(900000, collect($order['items'])->firstWhere('variant_id', 'red')['unit_price']);
        $variants = collect($p->fresh()->meta['variants'])->keyBy('id');
        $this->assertSame(0, $variants['red']['stock']);
        $this->assertSame(4, $variants['blue']['stock']);
        $this->assertSame(1, (int) SalesCoupon::query()->value('used'));

        $this->actingAs($this->admin(), 'sanctum');
        $this->patchJson('/api/v1/sales/storefront/orders/'.$order['id'], ['status' => 'cancelled'])->assertOk();
        $this->patchJson('/api/v1/sales/storefront/orders/'.$order['id'], ['status' => 'cancelled'])->assertOk();
        $variants = collect($p->fresh()->meta['variants'])->keyBy('id');
        $this->assertSame(2, $variants['red']['stock']);
        $this->assertSame(5, $variants['blue']['stock']);
        $this->assertSame(0, (int) SalesCoupon::query()->value('used'));
    }

    public function test_checkout_refuses_more_than_stock(): void
    {
        $p = $this->product(['stock' => 1]);
        $this->postJson('/api/v1/cart/items', ['product_id' => $p->id], $this->guest())->assertOk();
        $this->postJson('/api/v1/cart/items', ['product_id' => $p->id], $this->guest())->assertStatus(422);
        \Modules\Sales\Entities\SalesCartItem::query()->update(['quantity' => 2]);
        $this->postJson('/api/v1/checkout', $this->contact(), $this->guest())->assertStatus(422);
        $this->assertSame(0, SalesStorefrontOrder::query()->count());
    }

    public function test_gift_card_partial_full_expired_and_release(): void
    {
        $cards = app(StorefrontGiftCards::class);
        $card = $cards->issue(['amount_minor' => 300000]);
        $p = $this->product();
        $this->postJson('/api/v1/public/gift-cards/balance', ['code' => strtolower($card->code)])->assertOk()->assertJsonPath('data.balance_minor', 300000);

        $this->postJson('/api/v1/cart/items', ['product_id' => $p->id], $this->guest())->assertOk();
        $this->postJson('/api/v1/checkout', $this->contact(['gift_card_code' => 'WRONG']), $this->guest())->assertStatus(422)->assertJsonValidationErrors('gift_card_code');
        $order = $this->postJson('/api/v1/checkout', $this->contact(['gift_card_code' => $card->code]), $this->guest())->assertCreated()->json('data');
        // 1,000,000 + 500,000 shipping - 300,000 card
        $this->assertSame(1200000, $order['total_minor']);
        $this->assertSame('pending_payment', $order['status']);
        $this->assertSame(0, $card->fresh()->balance_minor);
        $this->assertSame(1200000, (int) SalesInvoice::query()->findOrFail($order['invoice_id'])->total);

        $this->actingAs($this->admin(), 'sanctum');
        $this->patchJson('/api/v1/sales/storefront/orders/'.$order['id'], ['status' => 'cancelled'])->assertOk();
        $this->assertSame(300000, $card->fresh()->balance_minor);

        $big = $cards->issue(['amount_minor' => 5000000]);
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/v1/cart/items', ['product_id' => $p->id], $this->guest())->assertOk();
        $paid = $this->postJson('/api/v1/checkout', $this->contact(['gift_card_code' => $big->code]), $this->guest())->assertCreated()->json('data');
        $this->assertSame(0, $paid['total_minor']);
        $this->assertSame('paid', $paid['status']);
        $this->assertSame(3500000, $big->fresh()->balance_minor);

        $old = $cards->issue(['amount_minor' => 100, 'expires_at' => now()->subDay()]);
        $this->postJson('/api/v1/cart/items', ['product_id' => $p->id], $this->guest())->assertOk();
        $this->postJson('/api/v1/checkout', $this->contact(['gift_card_code' => $old->code]), $this->guest())->assertStatus(422);
    }

    public function test_admin_gift_cards_crud(): void
    {
        $this->actingAs($this->admin(), 'sanctum');
        $id = $this->postJson('/api/v1/sales/storefront/gift-cards', ['amount_minor' => 200000, 'recipient_name' => 'علی'])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/sales/storefront/gift-cards/$id/adjust", ['direction' => 'debit', 'amount_minor' => 50000])->assertOk()->assertJsonPath('data.balance_minor', 150000);
        $this->postJson("/api/v1/sales/storefront/gift-cards/$id/adjust", ['direction' => 'debit', 'amount_minor' => 999999])->assertStatus(422);
        $this->putJson("/api/v1/sales/storefront/gift-cards/$id", ['status' => 'disabled'])->assertOk()->assertJsonPath('data.status', 'disabled');
        $this->getJson('/api/v1/sales/storefront/gift-cards')->assertOk()->assertJsonPath('meta.total', 1);
        $this->assertSame(16, strlen($this->postJson('/api/v1/sales/storefront/gift-cards/generate-code')->assertOk()->json('data.code')));
        $this->assertSame(1, SalesGiftCard::query()->count());
    }

    public function test_pricing_module_off_single_price_and_cash_only(): void
    {
        $p = $this->product(['sale_price' => 800000]);
        $this->postJson('/api/v1/cart/items', ['product_id' => $p->id], $this->guest())->assertOk()
            ->assertJsonPath('data.pricing.single_price', false)->assertJsonPath('data.pricing.payment_modes', ['cash', 'installment']);
        app(StorefrontSettings::class)->save(['pricing_enabled' => false]);
        $this->app->forgetInstance(StorefrontSettings::class);
        $cart = $this->getJson('/api/v1/cart', $this->guest())->assertOk()->json('data.pricing');
        $this->assertTrue($cart['single_price']);
        $this->assertSame(['cash'], $cart['payment_modes']);
        $this->assertSame(800000, $cart['subtotal_minor']);
        $this->getJson('/api/v1/payments/checkout-options')->assertOk()->assertJsonPath('data.modes', ['cash']);
        $order = $this->postJson('/api/v1/checkout', $this->contact(['payment_mode' => 'installment']), $this->guest())->assertCreated()->json('data');
        $this->assertSame('cash', $order['payment_mode']);
    }

    public function test_stepped_guest_checkout_with_delivery_time_per_method(): void
    {
        $this->actingAs($this->admin(), 'sanctum');
        $this->putJson('/api/v1/sales/storefront/settings', ['settings' => ['shipping_methods' => [
            ['id' => 'post', 'title' => 'پست', 'cost_minor' => 500000],
            ['id' => 'courier', 'title' => 'پیک', 'cost_minor' => 900000, 'free_over_minor' => 0],
        ]]])->assertOk();
        $saved = $this->putJson('/api/v1/sales/storefront/checkout-flow', ['data' => [
            'steps' => [['key' => 'delivery_time', 'enabled' => true, 'label' => 'زمان ارسال — تحویل']],
            'fields' => ['address' => [['key' => 'address_line', 'required' => true], ['key' => 'customer_email', 'enabled' => false]], 'payment' => [['key' => 'terms', 'enabled' => true, 'required' => true]]],
            'delivery_time' => ['mode' => 'methods', 'shipping_methods' => ['courier'], 'closed_weekdays' => []],
        ]])->assertOk()->json('data.settings');
        $this->assertStringNotContainsString('—', collect($saved['steps'])->firstWhere('key', 'delivery_time')['label']);
        $this->app['auth']->forgetGuards();
        $this->app->forgetInstance(StorefrontSettings::class);

        $cfg = $this->getJson('/api/v1/storefront/checkout-config', $this->guest())->assertOk()->json('data');
        $this->assertSame(['address', 'delivery_time', 'shipping', 'payment'], array_column($cfg['steps'], 'key'));
        $this->assertSame(['post', 'courier'], array_column($cfg['shipping_methods'], 'id'));
        $this->assertTrue(collect($cfg['shipping_methods'])->firstWhere('id', 'courier')['delivery_time_required']);
        $this->assertFalse(collect($cfg['fields']['address'])->contains('key', 'customer_email'));
        $this->assertNotEmpty($cfg['delivery_time']['days']);

        $p = $this->product();
        $this->postJson('/api/v1/cart/items', ['product_id' => $p->id], $this->guest())->assertOk();
        $this->postJson('/api/v1/checkout/steps/address', ['customer_name' => 'سارا', 'customer_phone' => '0912'], $this->guest())
            ->assertStatus(422)->assertJsonValidationErrors(['customer_phone', 'address_line']);
        $addr = $this->postJson('/api/v1/checkout/steps/address', ['customer_name' => 'سارا', 'customer_phone' => '09121234567', 'address_line' => 'تهران', 'shipping_state_code' => 'THR'], $this->guest())->assertOk()->json('data');
        $this->assertSame('delivery_time', $addr['next_step']);

        $this->postJson('/api/v1/checkout/steps/shipping', ['shipping_method' => 'drone'], $this->guest())->assertStatus(422);
        $ship = $this->postJson('/api/v1/checkout/steps/shipping', ['shipping_method' => 'post'], $this->guest())->assertOk()->json('data');
        $this->assertSame(500000, $ship['totals']['shipping_minor']);
        $this->assertFalse($ship['totals']['delivery_time_required']);
        $ship = $this->postJson('/api/v1/checkout/steps/shipping', ['shipping_method' => 'courier'], $this->guest())->assertOk()->json('data');
        $this->assertSame(900000, $ship['totals']['shipping_minor']);
        $this->assertTrue($ship['totals']['delivery_time_required']);

        $this->postJson('/api/v1/checkout/steps/delivery_time', [], $this->guest())->assertStatus(422)->assertJsonValidationErrors('delivery_date');
        $day = $cfg['delivery_time']['days'][0];
        $this->postJson('/api/v1/checkout/steps/delivery_time', ['delivery_date' => $day['date'], 'delivery_slot' => $day['slots'][0]['id']], $this->guest())->assertOk();

        $card = app(StorefrontGiftCards::class)->issue(['amount_minor' => 100000]);
        $this->postJson('/api/v1/checkout/steps/payment', ['gift_card_code' => $card->code], $this->guest())->assertStatus(422)->assertJsonValidationErrors('terms_accepted');
        $pay = $this->postJson('/api/v1/checkout/steps/payment', ['gift_card_code' => $card->code, 'terms_accepted' => true, 'payment_gateway' => 'zarinpal'], $this->guest())->assertOk()->json('data');
        $this->assertSame(1900000, $pay['totals']['total_before_gift_card_minor']);
        $this->assertSame(1800000, $pay['totals']['total_minor']);
        $this->assertNull($pay['next_step']);
        $this->getJson('/api/v1/checkout/session', $this->guest())->assertOk()->assertJsonPath('data.values.shipping_method', 'courier');

        $order = $this->postJson('/api/v1/checkout', [], $this->guest())->assertCreated()->json('data');
        $this->assertSame(1800000, $order['total_minor']);
        $row = SalesStorefrontOrder::query()->findOrFail($order['id']);
        $this->assertSame($day['date'], $row->meta['delivery_time']['date']);
        $this->assertSame('courier', $row->meta['shipping_method']['id']);
        $this->assertArrayHasKey('terms_accepted_at', $row->meta);
    }

    public function test_digital_cart_skips_shipping_and_delivery_steps(): void
    {
        app(StorefrontSettings::class)->save(['checkout_flow' => ['steps' => [['key' => 'delivery_time', 'enabled' => true]], 'fields' => ['address' => [['key' => 'address_line', 'required' => true]]]]]);
        $course = $this->product(['kind' => 'course'], 2000000);
        $this->postJson('/api/v1/cart/items', ['product_id' => $course->id], $this->guest())->assertOk();
        $step = $this->postJson('/api/v1/checkout/steps/address', ['customer_name' => 'سارا', 'customer_phone' => '09121234567'], $this->guest())->assertOk()->json('data');
        $this->assertSame('payment', $step['next_step']);
        $this->assertSame(0, $step['totals']['shipping_minor']);
        $this->postJson('/api/v1/checkout', ['customer_name' => 'سارا', 'customer_phone' => '09121234567'], $this->guest())->assertCreated()->assertJsonPath('data.total_minor', 2000000);
    }

    public function test_payment_callback_side_effect_marks_order_paid(): void
    {
        $p = $this->product();
        $this->postJson('/api/v1/cart/items', ['product_id' => $p->id], $this->guest())->assertOk();
        $order = $this->postJson('/api/v1/checkout', $this->contact(), $this->guest())->assertCreated()->json('data');
        $intent = PaymentIntent::query()->create([
            'public_id' => 'pi_test', 'callback_token' => 'cb_test', 'payable_type' => 'sales_invoice', 'payable_id' => (string) $order['invoice_id'],
            'mode' => 'cash', 'gateway' => 'zarinpal', 'currency' => 'IRR', 'base_amount' => $order['total_minor'],
            'total_amount' => $order['total_minor'], 'status' => 'paid',
        ]);
        app(PaymentSideEffects::class)->apply($intent);
        $this->assertSame('paid', SalesStorefrontOrder::query()->findOrFail($order['id'])->status);
        $this->getJson('/api/v1/storefront/orders/'.$order['id'], $this->guest())->assertOk()->assertJsonPath('data.status', 'paid');
    }

    public function test_every_gateway_has_translated_labels(): void
    {
        $store = app(GatewayConfigStore::class);
        foreach ($store->codes() as $code) {
            $g = $store->publicOne($code);
            $this->assertNotSame('', $g['label_fa'], $code);
            $this->assertNotSame('', $g['label_en'], $code);
            $this->assertMatchesRegularExpression('/[\x{0600}-\x{06FF}]/u', $g['label_fa'], "fa label of $code should be Persian");
            $this->assertDoesNotMatchRegularExpression('/[\x{2013}\x{2014}]/u', $g['label_fa'].$g['label_en'], $code);
        }
    }
}

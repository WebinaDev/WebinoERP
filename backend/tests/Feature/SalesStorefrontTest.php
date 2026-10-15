<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Core\Entities\SystemModule;
use Modules\Sales\Entities\SalesCatalogItem;
use Modules\Sales\Entities\SalesCoupon;
use Modules\Sales\Entities\SalesInvoice;
use Modules\Sales\Entities\SalesProductReview;
use Tests\TestCase;

class SalesStorefrontTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $meta = [], float $price = 1200000, string $status = 'active'): SalesCatalogItem
    {
        return SalesCatalogItem::query()->create(['name' => 'کرم '.($meta['slug'] ?? 'x'), 'sku' => 'SKU-'.uniqid(), 'price' => $price, 'status' => $status, 'type' => 'product', 'meta' => $meta + ['storefront' => true]]);
    }

    public function test_public_catalog_lists_only_storefront_items(): void
    {
        $a = $this->product(['slug' => 'cream', 'sale_price' => 1000000, 'category_slug' => 'skin', 'related_ids' => []]);
        $this->product(['slug' => 'serum', 'category_slug' => 'skin', 'stock' => 0]);
        SalesCatalogItem::query()->create(['name' => 'Consulting', 'price' => 5, 'status' => 'active', 'meta' => []]);
        $this->product(['slug' => 'old'], 1, 'inactive');

        $data = $this->getJson('/api/v1/public/catalog')->assertOk()->json('data');
        $this->assertSame(2, $data['pagination']['total']);
        $this->assertSame([['slug' => 'skin', 'name' => 'skin']], $data['categories']);

        $item = $this->getJson('/api/v1/public/catalog/items/cream')->assertOk()->json('data');
        $this->assertSame(1000000, $item['discounted_price_minor']);
        $this->assertSame(1200000, $item['price_minor']);
        $this->getJson('/api/v1/public/catalog/items/serum')->assertOk()->assertJsonPath('data.is_sold_out', true);
        $this->getJson('/api/v1/public/catalog/items/old')->assertNotFound();
        $this->getJson('/api/v1/public/catalog/items/cream/linked?type=related')->assertOk()->assertJsonCount(1, 'data.items');
        $this->getJson('/api/v1/public/catalog?q=serum')->assertOk()->assertJsonPath('data.pagination.total', 1);
        $this->assertSame($a->id, $this->getJson('/api/v1/public/catalog?sort=price_asc')->json('data.items.0.id'));
    }

    public function test_cart_checkout_coupon_and_payment_flow(): void
    {
        $p = $this->product(['slug' => 'cream', 'stock' => 5]);
        $sold = $this->product(['slug' => 'gone', 'stock' => 0]);
        SalesCoupon::query()->create(['code' => 'OFF10', 'type' => 'percent', 'value' => 10, 'min_total' => 0]);
        $this->getJson('/api/v1/cart')->assertUnauthorized();
        $this->actingAs(User::factory()->create(), 'sanctum');

        $this->postJson('/api/v1/cart/items', ['product_id' => $sold->id])->assertStatus(422);
        $this->postJson('/api/v1/cart/items', ['product_id' => $p->id, 'quantity' => 2])->assertOk()
            ->assertJsonPath('data.pricing.subtotal_minor', 2400000)->assertJsonPath('data.pricing.shipping_minor', 500000);
        $this->putJson("/api/v1/cart/items/{$p->id}", ['quantity' => 3])->assertOk()->assertJsonPath('data.items.0.quantity', 3);
        $this->postJson('/api/v1/public/coupons/preview', ['code' => 'off10', 'subtotal_minor' => 1000])->assertOk()->assertJsonPath('data.discount_minor', 100);
        $this->postJson('/api/v1/public/coupons/preview', ['code' => 'NOPE'])->assertStatus(422);
        $this->postJson('/api/v1/checkout', ['shipping_state_code' => 'XXX'])->assertStatus(422);

        $order = $this->postJson('/api/v1/checkout', ['coupon_code' => 'OFF10', 'customer_phone' => '09120000000', 'shipping_address' => ['line' => '<b>تهران</b>'], 'shipping_state_code' => 'THR'])
            ->assertCreated()->json('data');
        $this->assertSame(3600000, $order['subtotal_minor']);
        $this->assertSame(360000, $order['discount_minor']);
        $this->assertSame(3240000 + 500000, $order['total_minor']);
        $this->assertSame('تهران', $order['address']['line']);
        $this->assertSame('pending_payment', $order['status']);
        $this->assertSame(2, (int) $p->fresh()->meta['stock']);
        $this->assertSame(1, SalesCoupon::query()->value('used'));
        $this->getJson('/api/v1/cart')->assertOk()->assertJsonCount(0, 'data.items');

        $this->getJson('/api/v1/account/orders')->assertOk()->assertJsonPath('data.0.number', $order['number']);
        SalesInvoice::query()->whereKey($order['invoice_id'])->update(['status' => 'paid']);
        $this->getJson('/api/v1/account/orders')->assertOk()->assertJsonPath('data.0.status', 'processing');
        $this->postJson('/api/v1/public/orders/track', ['number' => $order['number'], 'phone' => '0912 000 0000'])->assertOk()->assertJsonPath('data.status', 'processing');
        $this->postJson('/api/v1/public/orders/track', ['number' => $order['number'], 'phone' => '0999'])->assertNotFound();
        $this->postJson('/api/v1/payments/intent', ['order_id' => $order['id']])->assertStatus(422);
        $this->getJson('/api/v1/payments/checkout-options')->assertOk()->assertJsonPath('data.gateways', []);
    }

    public function test_reviews_favorites_questions_compare_and_admin(): void
    {
        $p = $this->product(['slug' => 'cream', 'story_video_url' => 'https://cdn.example/v.mp4']);
        $this->postJson('/api/v1/public/catalog/items/cream/reviews', ['rating' => 4, 'body' => '<i>عالی</i>', 'author_name' => 'سارا'])->assertCreated();
        $this->getJson('/api/v1/public/catalog/items/cream/reviews')->assertOk()->assertJsonPath('data.total', 0);
        $review = SalesProductReview::query()->first();
        $this->postJson("/api/v1/public/catalog/reviews/{$review->id}/react")->assertNotFound();

        $this->postJson("/api/v1/public/compare/products/{$p->id}")->assertOk()->assertJsonPath('data.ids.0', $p->id);
        $this->getJson('/api/v1/public/compare')->assertOk()->assertJsonCount(1, 'data.items');
        $this->getJson('/api/v1/public/product-stories')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/public/geo/states')->assertOk()->assertJsonFragment(['code' => 'THR']);

        $customer = User::factory()->create();
        $this->actingAs($customer, 'sanctum');
        $this->postJson("/api/v1/account/favorites/{$p->id}")->assertOk();
        $this->getJson('/api/v1/account/favorites')->assertOk()->assertJsonPath('data.0.slug', 'cream');
        $this->postJson('/api/v1/account/questions', ['product_id' => $p->id, 'body' => 'برای پوست چرب مناسب است؟'])->assertCreated();
        $this->getJson('/api/v1/sales/storefront/reviews')->assertForbidden();

        SystemModule::query()->firstOrCreate(['slug' => 'sales'], ['name' => 'Sales', 'is_active' => true]);
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('system_manager');
        $this->actingAs($admin, 'sanctum');
        $this->patchJson("/api/v1/sales/storefront/reviews/{$review->id}", ['status' => 'approved'])->assertOk();
        $q = $this->getJson('/api/v1/sales/storefront/questions')->assertOk()->json('data.0.id');
        $this->patchJson("/api/v1/sales/storefront/questions/$q", ['answer' => 'بله', 'status' => 'answered'])->assertOk();
        $this->postJson('/api/v1/sales/storefront/coupons', ['code' => 'big', 'type' => 'percent', 'value' => 150])->assertStatus(422);
        $this->postJson('/api/v1/sales/storefront/coupons', ['code' => 'big', 'type' => 'fixed', 'value' => 1000])->assertCreated()->assertJsonPath('data.code', 'BIG');

        $this->getJson('/api/v1/public/catalog/items/cream/reviews')->assertOk()->assertJsonPath('data.items.0.body', 'عالی');
        $this->getJson('/api/v1/public/catalog/items/cream/questions')->assertOk()->assertJsonPath('data.items.0.answer', 'بله');
        $this->postJson("/api/v1/public/catalog/reviews/{$review->id}/react")->assertOk()->assertJsonPath('data.likes', 1);
        $this->postJson("/api/v1/public/catalog/reviews/{$review->id}/react")->assertOk()->assertJsonPath('data.likes', 1);
    }
}

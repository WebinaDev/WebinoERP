<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Accounting\Entities\AccProduct;
use Modules\Accounting\Entities\AccProductVariant;
use Modules\Sales\Services\Pos\PosPricing;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class PosApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->seedLicensedModules();
    }

    private function setupStore(): array
    {
        $wh = DB::table('scm_warehouses')->insertGetId(['name' => 'انبار فروشگاه', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $p = AccProduct::query()->create(['name' => 'قاب عکس', 'barcode' => '6260000000017', 'sell_price' => 100000, 'inventory_controlled' => true, 'category' => 'قاب']);
        $v = AccProductVariant::query()->create(['product_id' => $p->id, 'name' => 'با شیشه', 'barcode' => '6260000000024', 'sell_price' => 150000]);
        $svc = AccProduct::query()->create(['name' => 'بسته‌بندی کادو', 'sell_price' => 20000, 'inventory_controlled' => false]);
        DB::table('scm_warehouse_stock')->insert(['warehouse_id' => $wh, 'product_id' => $p->id, 'quantity' => 10, 'created_at' => now(), 'updated_at' => now()]);

        $reg = $this->postJson('/api/v1/sales/pos/registers', ['name' => 'صندوق ۱', 'warehouse_id' => $wh, 'tax_rate' => 10, 'tax_inclusive' => true])->assertCreated()->json('data');

        return [$wh, $p, $v, $svc, $reg];
    }

    public function test_pricing_spreads_order_discount_and_vat(): void
    {
        $r = PosPricing::calculate([
            ['quantity' => 2, 'unit_price' => 100, 'discount_amount' => 20],
            ['quantity' => 1, 'unit_price' => 120],
        ], 30, 0, 10, false);
        $this->assertSame(320.0, $r['subtotal']);
        $this->assertSame(50.0, $r['discount_total']);
        $this->assertSame(27.0, $r['tax_total']);
        $this->assertSame(297.0, $r['total']);

        $inc = PosPricing::calculate([['quantity' => 1, 'unit_price' => 110]], 0, 0, 10, true);
        $this->assertSame(110.0, $inc['total']);
        $this->assertSame(10.0, $inc['tax_total']);
    }

    public function test_full_shift_sale_split_payment_refund_and_z_report(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);
        [$wh, $p, $v, $svc, $reg] = $this->setupStore();

        // Barcode lookup finds the variant
        $this->getJson('/api/v1/sales/pos/products?barcode=6260000000024')->assertOk()
            ->assertJsonPath('data.items.0.id', $p->id)->assertJsonPath('data.items.0.matched_variant_id', $v->id);

        // Checkout needs a shift
        $this->postJson('/api/v1/sales/pos/orders', ['lines' => [['product_id' => $svc->id, 'quantity' => 1]], 'payments' => [['method' => 'cash', 'amount' => 20000]]])->assertStatus(422);

        $session = $this->postJson('/api/v1/sales/pos/sessions', ['register_id' => $reg['id'], 'opening_cash' => 500000])->assertCreated()->json('data');
        $this->postJson('/api/v1/sales/pos/sessions', ['register_id' => $reg['id']])->assertStatus(422);

        $cust = $this->postJson('/api/v1/sales/pos/customers', ['name' => 'مشتری تست', 'mobile' => '09120000000'])->assertCreated()->json('data');

        // 2 x variant (150k) + gift wrap (20k) = 320k, 20k order discount => 300k; card 200k + cash 150k => change 50k
        $res = $this->postJson('/api/v1/sales/pos/orders', [
            'lines' => [['variant_id' => $v->id, 'quantity' => 2], ['product_id' => $svc->id, 'quantity' => 1]],
            'discount_amount' => 20000,
            'customer_id' => $cust['id'],
            'payments' => [['method' => 'card', 'amount' => 200000, 'reference' => 'TRACE1'], ['method' => 'cash', 'amount' => 150000]],
        ])->assertCreated();
        $order = $res->json('data');
        $this->assertEquals(300000, $order['total']);
        $this->assertEquals(50000, $order['change_due']);
        $this->assertNotNull($order['sales_invoice_id']);
        $this->assertNotNull($order['journal_entry_id']);
        $this->assertEquals(8, (float) DB::table('scm_warehouse_stock')->where('product_id', $p->id)->value('quantity'));
        $this->assertSame(1, DB::table('scm_stock_ledger')->where('movement_type', 'pos_sale')->count());

        // Journal balances and VAT is split out (10% inclusive)
        $je = DB::table('acc_journal_lines')->where('journal_entry_id', $order['journal_entry_id']);
        $this->assertEquals((float) (clone $je)->sum('debit'), (float) (clone $je)->sum('credit'));
        $this->assertEqualsWithDelta(300000, (float) (clone $je)->sum('debit'), 0.01);
        $this->assertEqualsWithDelta(27272.73, (float) $order['tax_total'], 0.02);

        // Underpaid and card overpay are rejected
        $this->postJson('/api/v1/sales/pos/orders', ['lines' => [['product_id' => $svc->id, 'quantity' => 1]], 'payments' => [['method' => 'cash', 'amount' => 1000]]])->assertStatus(422);
        $this->postJson('/api/v1/sales/pos/orders', ['lines' => [['product_id' => $svc->id, 'quantity' => 1]], 'payments' => [['method' => 'card', 'amount' => 30000]]])->assertStatus(422);
        // Stock guard
        $this->postJson('/api/v1/sales/pos/orders', ['lines' => [['product_id' => $p->id, 'quantity' => 50]], 'payments' => [['method' => 'card', 'amount' => 5000000]]])->assertStatus(422);
        $this->assertEquals(8, (float) DB::table('scm_warehouse_stock')->where('product_id', $p->id)->value('quantity'));

        // Refund one framed item in cash, restocked
        $show = $this->getJson('/api/v1/sales/pos/orders/'.$order['id'])->assertOk();
        $line = collect($show->json('data.lines'))->firstWhere('variant_id', $v->id);
        $refund = $this->postJson('/api/v1/sales/pos/orders/'.$order['id'].'/refund', ['lines' => [['line_id' => $line['id'], 'quantity' => 1]], 'reason' => 'شکستگی'])->assertCreated()->json('data');
        $this->assertSame('refund', $refund['type']);
        $this->assertEquals(9, (float) DB::table('scm_warehouse_stock')->where('product_id', $p->id)->value('quantity'));
        $this->assertSame('partially_refunded', DB::table('pos_orders')->where('id', $order['id'])->value('status'));
        $this->postJson('/api/v1/sales/pos/orders/'.$order['id'].'/refund', ['lines' => [['line_id' => $line['id'], 'quantity' => 5]]])->assertStatus(422);

        // Cash out, then close with denominations
        $this->postJson('/api/v1/sales/pos/sessions/'.$session['id'].'/cash-movements', ['type' => 'out', 'amount' => 10000, 'reason' => 'خرید آب'])->assertCreated();
        $cur = $this->getJson('/api/v1/sales/pos/sessions/current')->assertOk();
        $expected = $cur->json('data.report.cash.expected');
        // 500k + (150k-50k) - refund cash - 10k
        $this->assertEqualsWithDelta(500000 + 100000 - (float) $refund['total'] - 10000, $expected, 0.01);

        $close = $this->postJson('/api/v1/sales/pos/sessions/'.$session['id'].'/close', ['denominations' => ['100000' => 5, '50000' => 1]])->assertOk();
        $this->assertSame(1, $close->json('data.report.z_number'));
        $this->assertEqualsWithDelta(550000 - $expected, (float) $close->json('data.session.cash_difference'), 0.01);
        $this->assertEquals(1, $close->json('data.report.orders_count'));
        $this->assertEquals(1, $close->json('data.report.refunds_count'));

        $this->getJson('/api/v1/sales/pos/reports/daily')->assertOk()->assertJsonPath('data.summary.orders_count', 1);
        $this->postJson('/api/v1/sales/pos/sessions/'.$session['id'].'/cash-movements', ['type' => 'in', 'amount' => 1])->assertStatus(422);
    }

    public function test_cashier_can_sell_but_not_refund_or_manage(): void
    {
        $admin = $this->actingAsRole('system_manager');
        Sanctum::actingAs($admin);
        [, , , $svc, $reg] = $this->setupStore();

        $cashier = $this->actingAsRole('sales_consultant');
        Sanctum::actingAs($cashier);
        $this->postJson('/api/v1/sales/pos/registers', ['name' => 'x'])->assertForbidden();
        $this->postJson('/api/v1/sales/pos/sessions', ['register_id' => $reg['id'], 'opening_cash' => 0])->assertCreated();
        $order = $this->postJson('/api/v1/sales/pos/orders', [
            'lines' => [['product_id' => $svc->id, 'quantity' => 1, 'unit_price' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 20000]],
        ])->assertCreated()->json('data');
        // Price override ignored for a cashier
        $this->assertEquals(20000, $order['total']);
        $this->postJson('/api/v1/sales/pos/orders/'.$order['id'].'/refund', ['lines' => [['line_id' => $order['lines'][0]['id'], 'quantity' => 1]]])->assertForbidden();
        $this->getJson('/api/v1/sales/pos/reports/daily')->assertForbidden();

        $client = $this->actingAsRole('client');
        Sanctum::actingAs($client);
        $this->getJson('/api/v1/sales/pos/overview')->assertForbidden();
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Entities\SystemModule;
use Modules\Sales\Entities\SalesInvoice;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class SalesApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        SystemModule::create(['name' => 'Sales', 'slug' => 'sales', 'is_active' => true]);
    }

    public function test_invoice_pdf_email_and_services(): void
    {
        Mail::fake();

        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $invoice = SalesInvoice::create([
            'number' => 'INV-100',
            'invoice_number' => 'INV-100',
            'customer_name' => 'ACME',
            'total' => 1000000,
            'subtotal' => 1000000,
            'discount' => 0,
            'status' => 'draft',
            'issue_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'items' => [
                ['title' => 'Service A', 'desc' => 'Desc', 'price' => 1000000, 'discount' => 0],
            ],
            'created_by' => $user->id,
        ]);

        $this->postJson('/api/v1/sales/invoices/'.$invoice->id.'/pdf')
            ->assertOk()
            ->assertJsonPath('data.mime', 'application/pdf');

        $this->postJson('/api/v1/sales/invoices/'.$invoice->id.'/email', ['email' => 'billing@acme.test'])
            ->assertOk()
            ->assertJsonPath('data.sent_to', 'billing@acme.test');

        Mail::assertSent(\App\Mail\SalesInvoiceMail::class, 1);

        $this->getJson('/api/v1/sales/services/subscriptions')->assertOk();
        $this->getJson('/api/v1/sales/services/products')->assertOk();
        $this->getJson('/api/v1/sales/services/task-templates')->assertOk();
    }

    public function test_invoice_store_with_line_items(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $res = $this->postJson('/api/v1/sales/invoices', [
            'customer_name' => 'Buyer Co',
            'issue_date' => '2026-09-13',
            'payment_method' => 'transfer',
            'items' => [
                ['title' => 'Design', 'desc' => 'UI', 'price' => 500, 'discount' => 50],
                ['title' => 'Dev', 'desc' => 'API', 'price' => 1500, 'discount' => 0],
            ],
        ])->assertCreated();

        $this->assertEquals(2000.0, (float) $res->json('data.subtotal'));
        $this->assertEquals(50.0, (float) $res->json('data.discount'));
        $this->assertEquals(1950.0, (float) $res->json('data.total'));
        $this->assertNotEmpty($res->json('data.invoice_number') ?? $res->json('data.number'));
    }

    public function test_campaign_crud_and_filters(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $created = $this->postJson('/api/v1/sales/campaigns', [
            'name' => 'Spring Ads',
            'channel' => 'google',
            'status' => 'active',
            'budget' => 5000000,
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-31',
        ])->assertCreated();

        $id = $created->json('data.id');
        $this->assertNotEmpty($id);

        $this->getJson('/api/v1/sales/campaigns?channel=google&status=active')
            ->assertOk();

        $this->deleteJson('/api/v1/sales/campaigns/'.$id)->assertNoContent();
    }

    public function test_rahn_calculate_quote_lock_and_public(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/sales/rahn/settings')->assertOk();

        $items = [
            ['id' => '1', 'name' => 'Site', 'billing' => 'once', 'amount' => 30000000],
        ];

        $calc = $this->postJson('/api/v1/sales/rahn/calculate', [
            'items_snapshot' => $items,
            'T' => 6,
            's_hat' => 50000000,
            'mode' => 'from_p',
            'p_wanted' => 0.1,
        ])->assertOk();

        $lock = $calc->json('data.lock') ?? $calc->json('data');
        $this->assertIsArray($lock);
        $this->assertGreaterThan(0, (float) ($lock['C'] ?? 0));
        $this->assertArrayHasKey('F', $lock);
        $this->assertArrayHasKey('p', $lock);

        $quote = $this->postJson('/api/v1/sales/rahn/quotes', [
            'title' => 'Demo quote',
            'customer_name' => 'ACME',
            'items_snapshot' => $items,
            'T' => 6,
            's_hat' => 50000000,
            'p_wanted' => 0.1,
            'mode' => 'from_p',
        ])->assertCreated();

        $quoteId = (int) (
            $quote->json('data.quote.id')
            ?? $quote->json('data.id')
            ?? 0
        );
        $this->assertGreaterThan(0, $quoteId);

        $this->postJson('/api/v1/sales/rahn/quotes/'.$quoteId.'/lock', [])
            ->assertOk();

        $token = (string) (
            $quote->json('data.quote.public_token')
            ?? $quote->json('data.public_token')
            ?? ''
        );

        if ($token === '') {
            $list = $this->getJson('/api/v1/sales/rahn/quotes')->assertOk();
            $payload = $list->json('data');
            $rows = is_array($payload['quotes'] ?? null) ? $payload['quotes'] : (is_array($payload) ? $payload : []);
            foreach ($rows as $row) {
                if (is_array($row) && (int) ($row['id'] ?? 0) === $quoteId) {
                    $token = (string) ($row['public_token'] ?? '');
                    break;
                }
            }
        }

        if ($token !== '') {
            $this->getJson('/api/v1/sales/rahn/public/'.$token)->assertOk();
        }
    }
}
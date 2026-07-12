<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $invoice = SalesInvoice::create([
            'number' => 'INV-100',
            'customer_name' => 'ACME',
            'total' => 1000000,
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        $this->postJson('/api/v1/sales/invoices/'.$invoice->id.'/pdf')->assertOk();
        $this->postJson('/api/v1/sales/invoices/'.$invoice->id.'/email', ['email' => 'billing@acme.test'])->assertOk();
        $this->getJson('/api/v1/sales/services/subscriptions')->assertOk();
        $this->getJson('/api/v1/sales/services/products')->assertOk();
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Crm\Entities\CrmBasalamOrder;
use Modules\Crm\Http\Controllers\Customer360\BasalamOrderIngestController;
use Tests\TestCase;

/** Queue cap aligned with WebinaDashboard plugin 0.9.65 (8db9d20, MAX_QUEUE = 5000, drop oldest). */
class BasalamOrderIngestCapTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/webinocrm/v1/crm/basalam/orders';

    public function test_cap_constant_matches_plugin(): void
    {
        $this->assertSame(5000, BasalamOrderIngestController::MAX_QUEUE);
    }

    public function test_oldest_orders_are_dropped_when_over_cap_per_site(): void
    {
        foreach (['A', 'B', 'C', 'D'] as $i => $id) {
            CrmBasalamOrder::query()->create(['site_domain' => 's.ir', 'external_id' => $id, 'total' => 1])
                ->forceFill(['updated_at' => now()->subMinutes(10 - $i)])->saveQuietly();
        }
        CrmBasalamOrder::query()->create(['site_domain' => 'other.ir', 'external_id' => 'X', 'total' => 1]);

        $this->assertSame(2, BasalamOrderIngestController::enforceCap('s.ir', 2));
        $this->assertSame(['C', 'D'], CrmBasalamOrder::query()->where('site_domain', 's.ir')->orderBy('external_id')->pluck('external_id')->all());
        $this->assertSame(1, CrmBasalamOrder::query()->where('site_domain', 'other.ir')->count());
        $this->assertSame(0, BasalamOrderIngestController::enforceCap('s.ir', 2));
    }

    public function test_ingest_under_cap_drops_nothing(): void
    {
        $this->postJson(self::URL, ['site_url' => 'https://s.ir', 'orders' => [['invoice_id' => 'I1', 'total' => 5]]])
            ->assertOk()->assertJsonPath('data.dropped', 0);
        $this->assertSame(1, CrmBasalamOrder::query()->count());
    }
}

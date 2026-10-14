<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Modules\Crm\Entities\CrmLead;
use Modules\Sales\Entities\SalesRahnQuote;
use Tests\TestCase;

class RahnSitePublicApiTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/api/v1/sales/rahn/site';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_config_is_public_and_hides_internal_costs(): void
    {
        $res = $this->getJson(self::BASE.'/config')->assertOk()
            ->assertJsonStructure(['data' => ['catalog', 'wizard_steps', 'duration_options', 'public' => ['F', 'p', 'T'], 'p_min', 'p_max']])
            ->assertJsonPath('data.token', null);

        $json = json_encode($res->json('data'));
        foreach (['"C"', '"V_star"', '"F_min"', '"S_BE"', '"breakdown"', '"m"', '"k"', '"internal"', '"lock"'] as $key) {
            $this->assertStringNotContainsString($key.':', $json, "config leaks $key");
        }
        $this->assertNotEmpty($res->json('data.catalog'));
    }

    public function test_calculate_ignores_client_price_overrides(): void
    {
        $id = $this->getJson(self::BASE.'/config')->json('data.catalog.0.id');
        $base = $this->postJson(self::BASE.'/calculate', ['selected_ids' => [$id]])->assertOk()->json('data.public.F');
        $tampered = $this->postJson(self::BASE.'/calculate', [
            'selected_ids' => [$id],
            'items_snapshot' => [['id' => $id, 'name' => 'x', 'billing' => 'once', 'amount' => 1]],
        ])->assertOk();
        $this->assertSame($base, $tampered->json('data.public.F'));
        $this->assertNull($tampered->json('data.internal'));
    }

    public function test_submit_creates_lead_and_website_quote(): void
    {
        $id = $this->getJson(self::BASE.'/config')->json('data.catalog.0.id');
        $this->postJson(self::BASE.'/submit', ['selected_ids' => [$id], 'phone' => '0912'])->assertStatus(422);
        $this->postJson(self::BASE.'/submit', ['name' => 'مینا', 'phone' => '09120000003', 'selected_ids' => [], '_t' => 5000])->assertStatus(400);

        $this->postJson(self::BASE.'/submit', [
            'name' => 'مینا احمدی', 'phone' => '09120000003', 'business_name' => 'گالری مینا',
            'selected_ids' => [$id], '_hp' => '', '_t' => 5000,
        ])->assertOk()->assertJsonStructure(['data' => ['lead_id', 'quote_id', 'public']]);

        $lead = CrmLead::query()->firstOrFail();
        $this->assertStringContainsString('منبع: وب‌سایت', $lead->description);
        $quote = SalesRahnQuote::query()->firstOrFail();
        $this->assertSame('draft', $quote->status);
        $this->assertSame((int) $lead->id, (int) $quote->lead_id);
        $this->assertSame('website', $quote->calc_snapshot['source']);
        $this->assertStringStartsWith('وب‌سایت — گالری مینا', $quote->title);
    }

    public function test_honeypot_and_too_fast_submissions_are_dropped(): void
    {
        $this->postJson(self::BASE.'/submit', ['name' => 'bot', 'phone' => '1', 'selected_ids' => ['x'], '_hp' => 'spam'])->assertOk();
        $this->postJson(self::BASE.'/submit', ['name' => 'bot', 'phone' => '1', 'selected_ids' => ['x'], '_t' => 10])->assertOk();
        $this->assertSame(0, SalesRahnQuote::query()->count());
        $this->assertSame(0, CrmLead::query()->count());
    }

    public function test_submit_is_rate_limited(): void
    {
        $this->withMiddleware(ThrottleRequests::class);
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = $this->postJson(self::BASE.'/submit', ['name' => 'bot', 'phone' => '1', '_hp' => 'x'])->status();
        }
        $this->assertContains(429, $codes);
    }
}

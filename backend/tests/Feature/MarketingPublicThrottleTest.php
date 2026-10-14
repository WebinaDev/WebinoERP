<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingPublicThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_internal_ssr_calls_are_not_throttled_but_proxied_visitors_are(): void
    {
        for ($i = 0; $i < 125; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.5'])->getJson('/api/v1/public/faq')->assertOk();
        }
        for ($i = 0; $i < 30; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.9'])->withHeader('X-Forwarded-For', '5.6.7.8')->getJson('/api/v1/public/faq')->assertOk();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.9'])->withHeader('X-Forwarded-For', '5.6.7.8')->getJson('/api/v1/public/faq')->assertStatus(429);
    }
}

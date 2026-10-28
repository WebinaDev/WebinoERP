<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** X-Public-Cache was missing on HEAD probes (curl -I) and on uncached/bypassed responses. */
class PublicCacheHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_header_on_get_head_and_bypass(): void
    {
        config(['services.public_api_cache' => true, 'cache.default' => 'array']);
        $this->getJson('/api/v1/public/site')->assertHeader('X-Public-Cache');
        $this->call('HEAD', '/api/v1/public/site')->assertHeader('X-Public-Cache');
        $this->withHeader('Authorization', 'Bearer x')->getJson('/api/v1/public/site')->assertHeader('X-Public-Cache', 'BYPASS');
    }
}

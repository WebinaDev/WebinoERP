<?php

namespace Tests\Feature;

use Tests\TestCase;

class CoreConfigApiTest extends TestCase
{
    public function test_public_config_endpoint_returns_success_structure(): void
    {
        $response = $this->getJson('/api/v1/core/config');

        $response->assertOk();
        $response->assertJsonStructure(['data']);
    }

    public function test_pwa_manifest_is_public(): void
    {
        $this->getJson('/api/v1/core/manifest.json')->assertOk();
    }
}

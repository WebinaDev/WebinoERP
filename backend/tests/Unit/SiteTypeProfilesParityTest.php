<?php

namespace Tests\Unit;

use Modules\Platform\Support\SiteTypeProfiles;
use Tests\TestCase;

class SiteTypeProfilesParityTest extends TestCase
{
    public function test_ecommerce_includes_commerce_wallet_pos_modules(): void
    {
        $modules = SiteTypeProfiles::modulesFor('ecommerce');
        $this->assertNotNull($modules);
        $commerce = $modules['commerce'] ?? [];
        foreach (['brands', 'attributes', 'pricing', 'c2c', 'wallet', 'pos'] as $sub) {
            $this->assertContains($sub, $commerce, "Missing commerce submodule: {$sub}");
        }
        $this->assertArrayHasKey('bots', $modules);
        $this->assertArrayHasKey('sms-panel', $modules);
    }
}

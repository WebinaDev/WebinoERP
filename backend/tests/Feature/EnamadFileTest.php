<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Entities\SystemModule;
use Modules\Marketing\Entities\MarketingSiteSetting;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class EnamadFileTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        SystemModule::query()->updateOrCreate(['slug' => 'marketing'], ['name' => 'M', 'is_active' => true]);
        Sanctum::actingAs($this->actingAsRole('system_manager'));
    }

    public function test_enamad_code_is_an_empty_public_root_file(): void
    {
        $this->putJson('/api/v1/marketing/settings', [
            'branding' => ['enamad_file' => '4534534.txt', 'primary' => '#0054ff'],
        ])->assertOk();

        $this->assertSame('4534534', MarketingSiteSetting::current()->fresh()->branding['enamad_file']);

        $ok = $this->get('/api/v1/public/enamad/4534534');
        $ok->assertOk();
        $ok->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $this->assertSame('', $ok->getContent());

        $this->get('/api/v1/public/enamad/111111')->assertNotFound();
    }
}

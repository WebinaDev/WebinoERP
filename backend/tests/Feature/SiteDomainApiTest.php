<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\SiteBuilder\Database\Seeders\SiteBuilderSeeder;
use Modules\SiteBuilder\Entities\WebinoPackage;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;
use Modules\SiteBuilder\Services\SiteProvisionOrchestrator;
use Modules\Core\Entities\SystemModule;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class SiteDomainApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        SystemModule::query()->firstOrCreate(['slug' => 'platform'], ['name' => 'Platform', 'is_active' => true]);
        $this->seed(SiteBuilderSeeder::class);
    }

    private function site(string $domain): int
    {
        $id = $this->postJson('/api/v1/site-builder/provisions', [
            'package_id' => WebinoPackage::query()->first()->id,
            'wizard_payload' => ['site_name' => 'S '.$domain],
        ])->assertCreated()->json('data.id');
        WebinoSiteProvision::query()->whereKey($id)->update(['domain' => $domain, 'status' => WebinoSiteProvision::STATUS_READY]);

        return $id;
    }

    public function test_domain_crud_and_global_uniqueness(): void
    {
        Sanctum::actingAs($this->actingAsRole('system_manager'));
        $this->mock(SiteProvisionOrchestrator::class, fn ($m) => $m->shouldReceive('applySiteDomains')->andReturn(['ok' => true, 'applied' => true, 'log' => 'x']));
        $a = $this->site('hassandoust.ir');
        $b = $this->site('other.ir');

        $this->getJson("/api/v1/site-builder/provisions/{$a}/domains")->assertOk()
            ->assertJsonPath('data.0.domain', 'hassandoust.ir')->assertJsonPath('data.0.role', 'primary');

        $r = $this->postJson("/api/v1/site-builder/provisions/{$a}/domains", [
            'domain' => 'https://WWW.FatemehHasandust.ir/', 'role' => 'redirect', 'redirect_path' => 'fa',
        ])->assertCreated();
        $item = $r->json('meta.item');
        $r->assertJsonPath('meta.caddy.applied', true);
        $this->assertSame('fatemehhasandust.ir', $item['domain']);
        $this->assertSame('/fa', $item['redirect_path']);
        $this->assertSame(301, $item['redirect_status']);

        $this->postJson("/api/v1/site-builder/provisions/{$b}/domains", ['domain' => 'fatemehhasandust.ir', 'role' => 'alias'])->assertStatus(422);
        $this->postJson("/api/v1/site-builder/provisions/{$a}/domains", ['domain' => 'other.ir', 'role' => 'alias'])->assertStatus(422);
        $this->postJson("/api/v1/site-builder/provisions/{$a}/domains", ['domain' => 'bad domain', 'role' => 'alias'])->assertStatus(422);

        $this->patchJson("/api/v1/site-builder/provisions/{$a}/domains/{$item['id']}", ['redirect_status' => 302])
            ->assertOk()->assertJsonPath('meta.item.redirect_status', 302);
        $this->patchJson("/api/v1/site-builder/provisions/{$b}/domains/{$item['id']}", ['redirect_status' => 302])->assertNotFound();

        $primaryId = $this->getJson("/api/v1/site-builder/provisions/{$a}/domains")->json('data.0.id');
        $this->deleteJson("/api/v1/site-builder/provisions/{$a}/domains/{$primaryId}")->assertStatus(422);
        $this->deleteJson("/api/v1/site-builder/provisions/{$a}/domains/{$item['id']}")->assertOk()->assertJsonCount(1, 'data');
    }
}

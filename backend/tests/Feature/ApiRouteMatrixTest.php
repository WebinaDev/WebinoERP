<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Entities\SystemModule;
use Modules\Integrations\Entities\IntegrationSetting;
use Modules\Integrations\Entities\ModirPayamakPackage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

#[Group('matrix')]
class ApiRouteMatrixTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        $this->seedLicensedModules();
        SystemModule::query()->where('slug', 'mfg')->update(['is_active' => true]);

        IntegrationSetting::putString('modirpayamak', 'enabled', '1');
        IntegrationSetting::putString('modirpayamak', 'api_key', 'test-key');
        ModirPayamakPackage::firstOrCreate(
            ['name' => 'Starter'],
            ['amount' => 100000, 'sms_units' => 200, 'is_active' => true, 'sort_order' => 1]
        );
        IntegrationSetting::putJson('bale', 'settings', [
            'bot_token' => 'test-bale-token',
            'welcome_text' => 'Hello',
        ]);
    }

    public static function apiSmokeProvider(): array
    {
        $path = dirname(__DIR__).'/fixtures/routes.manifest.json';
        $manifest = json_decode((string) file_get_contents($path), true);
        $cases = [];

        foreach ($manifest['routes'] ?? [] as $row) {
            if (! isset($row['apiSmoke'])) {
                continue;
            }
            $label = $row['route'] === '' ? 'dashboard' : $row['route'];
            $cases[$label] = [$row];
        }

        return $cases;
    }

    #[DataProvider('apiSmokeProvider')]
    public function test_api_smoke_get(array $row): void
    {
        $smoke = $row['apiSmoke'];
        $role = $smoke['role'] ?? 'system_manager';
        $user = $this->actingAsRole($role);
        Sanctum::actingAs($user);

        if (! empty($smoke['requiresModules'])) {
            foreach ($smoke['requiresModules'] as $slug) {
                SystemModule::query()->where('slug', $slug)->update(['is_active' => true]);
            }
        }

        $response = $this->getJson($smoke['path']);

        $allowed = [200, 204];
        if (! empty($smoke['allowNotFound'])) {
            $allowed[] = 404;
        }

        $this->assertContains(
            $response->status(),
            $allowed,
            sprintf(
                'Unexpected status %d for %s (%s) as %s: %s',
                $response->status(),
                $row['route'] ?: 'dashboard',
                $smoke['path'],
                $role,
                $response->getContent()
            )
        );

        $this->assertNotEquals(500, $response->status(), 'Server error on '.$smoke['path']);
        if ($role === 'system_manager' && ! ($smoke['allowNotFound'] ?? false)) {
            $this->assertNotEquals(403, $response->status(), 'Forbidden for system_manager on '.$smoke['path']);
        }
    }
}

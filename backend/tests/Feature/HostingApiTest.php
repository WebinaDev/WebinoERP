<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Entities\CoreHostingSetting;
use Modules\Core\Entities\ModuleGitSource;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class HostingApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
    }

    public function test_system_manager_can_read_and_update_hosting_settings(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $this->getJson('/api/webinocrm/v1/hosting/settings')
            ->assertOk()
            ->assertJsonStructure(['data' => ['public_crm_url', 'git_provider']]);

        $this->putJson('/api/webinocrm/v1/hosting/settings', [
            'public_crm_url' => 'https://crm.example.test',
            'git_provider' => 'gitea',
        ])->assertOk()
            ->assertJsonPath('data.public_crm_url', 'https://crm.example.test');

        $this->assertSame(
            'https://crm.example.test',
            CoreHostingSetting::current()->public_crm_url
        );
    }

    public function test_system_manager_can_manage_git_sources(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/webinocrm/v1/hosting/module-git-sources', [
            'slug' => 'demo_mod',
            'clone_url' => 'https://git.example.test/org/demo.git',
            'auth_type' => 'none',
        ]);
        $create->assertCreated();
        $id = $create->json('data.id');

        $this->getJson('/api/webinocrm/v1/hosting/module-git-sources')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'demo_mod']);

        $this->patchJson("/api/webinocrm/v1/hosting/module-git-sources/{$id}", [
            'clone_url' => 'https://git.example.test/org/demo-v2.git',
        ])->assertOk()
            ->assertJsonPath('data.clone_url', 'https://git.example.test/org/demo-v2.git');

        $this->deleteJson("/api/webinocrm/v1/hosting/module-git-sources/{$id}")
            ->assertNoContent();

        $this->assertNull(ModuleGitSource::query()->find($id));
    }

    public function test_non_system_manager_gets_403(): void
    {
        $user = $this->actingAsRole('team_member');
        Sanctum::actingAs($user);

        $this->getJson('/api/webinocrm/v1/hosting/settings')->assertForbidden();
    }

    public function test_portainer_stacks_returns_503_when_unconfigured(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $s = CoreHostingSetting::current();
        $s->portainer_url = null;
        $s->portainer_api_token = null;
        $s->save();

        $this->getJson('/api/webinocrm/v1/hosting/portainer/stacks')
            ->assertStatus(503);
    }
}

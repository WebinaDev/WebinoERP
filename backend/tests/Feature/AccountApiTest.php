<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Core\Entities\SystemModule;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class AccountApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        SystemModule::create(['name' => 'CRM', 'slug' => 'crm', 'is_active' => true]);
    }

    public function test_account_crud_and_index_filters(): void
    {
        $user = $this->actingAsRole(RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER);
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/v1/crm/accounts', [
            'name' => 'ACME Corp',
            'type' => 'customer',
        ]);
        $create->assertCreated();
        $id = $create->json('data.id');

        $this->getJson('/api/v1/crm/accounts?search=ACME')->assertOk()
            ->assertJsonPath('data.0.name', 'ACME Corp');

        $this->patchJson('/api/v1/crm/accounts/'.$id, ['name' => 'ACME Updated'])->assertOk();
        $this->getJson('/api/v1/crm/accounts/'.$id)->assertOk();
        $this->deleteJson('/api/v1/crm/accounts/'.$id)->assertStatus(204);
    }
}

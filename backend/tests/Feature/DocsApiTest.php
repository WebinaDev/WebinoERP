<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Entities\SystemModule;
use Modules\Docs\Entities\DocsContract;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class DocsApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        SystemModule::create(['name' => 'Docs', 'slug' => 'docs', 'is_active' => true]);
        Storage::fake('local');
    }

    public function test_file_upload_and_contract_cancel(): void
    {
        $user = $this->actingAsRole('system_manager');
        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->create('spec.pdf', 100, 'application/pdf');
        $upload = $this->post('/api/v1/docs/files', [
            'file' => $file,
        ], ['Accept' => 'application/json']);
        $upload->assertCreated();

        $contract = DocsContract::create([
            'title' => 'Service Agreement',
            'status' => 'active',
            'created_by' => $user->id,
        ]);
        $this->postJson('/api/v1/docs/contracts/'.$contract->id.'/cancel')->assertOk();
        $this->postJson('/api/v1/docs/contracts/'.$contract->id.'/projects', ['project_id' => 42])->assertOk();
    }
}

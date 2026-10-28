<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Entities\SystemModule;
use Modules\Poetry\Entities\PoetryMedia;
use Modules\Poetry\Entities\PoetryPoem;
use Modules\Poetry\Entities\PoetryTerm;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class PoetryMediaTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        SystemModule::query()->updateOrCreate(['slug' => 'marketing'], ['name' => 'M', 'is_active' => true]);
    }

    private function poem(array $extra = []): int
    {
        Sanctum::actingAs($this->actingAsRole('system_manager'));

        return $this->postJson('/api/v1/poetry/poems', ['title' => 'شب', 'status' => 'published', 'stanzas' => [['type' => 'stanza', 'lines' => ['a', 'b']]]] + $extra)->assertCreated()->json('data.id');
    }

    public function test_admin_crud_and_validation(): void
    {
        $poemId = $this->poem();
        $occ = PoetryTerm::query()->where('kind', 'occasion')->value('id');
        $this->postJson('/api/v1/poetry/media', ['title' => 'بی‌فایل', 'kind' => 'audio'])->assertStatus(422)->assertJsonValidationErrors('file_url');
        $this->postJson('/api/v1/poetry/media', ['title' => 'x', 'kind' => 'gif', 'file_url' => '/storage/a.mp3'])->assertStatus(422);
        $this->postJson('/api/v1/poetry/media', ['title' => 'x', 'kind' => 'audio', 'external_url' => 'javascript:alert(1)'])->assertStatus(422);
        $this->postJson('/api/v1/poetry/media', ['title' => 'x', 'kind' => 'audio', 'file_url' => '/storage/a.mp3', 'poem_id' => 99999])->assertStatus(422);
        $id = $this->postJson('/api/v1/poetry/media', ['title' => 'نوحه', 'kind' => 'video', 'external_url' => 'https://www.aparat.com/v/abc12', 'occasion_id' => $occ, 'poem_id' => $poemId])
            ->assertCreated()->assertJsonPath('data.embed_url', 'https://www.aparat.com/video/video/embed/videohash/abc12/vt/frame')->assertJsonPath('data.poem.id', $poemId)->json('data.id');
        $this->putJson("/api/v1/poetry/media/{$id}", ['title' => 'نوحه ۲'])->assertOk()->assertJsonPath('data.title', 'نوحه ۲')->assertJsonPath('data.status', 'published');
        $this->getJson('/api/v1/poetry/media')->assertOk()->assertJsonPath('meta.total', 1);
        $this->deleteJson("/api/v1/poetry/media/{$id}")->assertOk();
        $this->assertSame(0, PoetryMedia::query()->count());
    }

    public function test_public_lists_only_published_playable_items(): void
    {
        PoetryMedia::query()->create(['title' => 'صوت', 'kind' => 'audio', 'file_url' => '/storage/a.mp3']);
        PoetryMedia::query()->create(['title' => 'ویدیو', 'kind' => 'video', 'external_url' => 'https://eitaa.com/c/1']);
        PoetryMedia::query()->create(['title' => 'خالی', 'kind' => 'audio']);
        PoetryMedia::query()->create(['title' => 'پیش‌نویس', 'kind' => 'audio', 'file_url' => '/storage/b.mp3', 'status' => 'draft']);

        $this->getJson('/api/v1/public/poetry/media')->assertOk()->assertJsonCount(2, 'data')->assertJsonMissingPath('data.0.status');
        $this->getJson('/api/v1/public/poetry/media?kind=video')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'ویدیو');
    }

    public function test_poem_detail_merges_standalone_media_and_has_media(): void
    {
        $id = $this->poem();
        $slug = PoetryPoem::query()->find($id)->slug;
        $this->getJson("/api/v1/public/poetry/poems/{$slug}")->assertOk()->assertJsonPath('data.has_media', false)->assertJsonCount(0, 'data.media');
        PoetryMedia::query()->create(['title' => 'اجرا', 'kind' => 'audio', 'file_url' => '/storage/a.mp3', 'poem_id' => $id]);
        PoetryMedia::query()->create(['title' => 'آپارات', 'kind' => 'video', 'external_url' => 'https://aparat.com/v/zz', 'poem_id' => $id]);
        $this->getJson("/api/v1/public/poetry/poems/{$slug}")->assertOk()->assertJsonCount(1, 'data.media')->assertJsonCount(1, 'data.external_links');
        $this->getJson('/api/v1/public/poetry/poems?has_media=1')->assertOk()->assertJsonPath('data.0.has_media', true);
    }

    public function test_backfill_migration_copies_poem_links(): void
    {
        $id = $this->poem(['external_links' => [['label' => 'ایتا', 'url' => 'https://eitaa.com/x/1'], ['label' => 'آپارات', 'url' => 'https://www.aparat.com/v/q1']]]);
        DB::table('poetry_media')->delete();
        $migration = require module_path('Poetry', 'Database/Migrations/2026_10_28_120000_create_poetry_media_table.php');
        $migration->backfill();
        $rows = PoetryMedia::query()->where('poem_id', $id)->orderBy('id')->get();
        $this->assertSame(['audio', 'video'], $rows->pluck('kind')->all());
        $migration->backfill(); // idempotent
        $this->assertSame(2, PoetryMedia::query()->count());
    }
}

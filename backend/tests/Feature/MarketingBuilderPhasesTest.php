<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Core\Entities\SystemModule;
use Modules\Marketing\Entities\BuilderRevision;
use Modules\Marketing\Entities\MarketingPage;
use Modules\Marketing\Entities\SeoRedirect;
use Tests\TestCase;

class MarketingBuilderPhasesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        SystemModule::query()->firstOrCreate(['slug' => 'marketing'], ['name' => 'Marketing', 'is_active' => true]);
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('system_manager');
        $this->actingAs($this->admin, 'sanctum');
    }

    private function doc(string $text, string $sec = 's'): array
    {
        return ['version' => 2, 'sections' => [['id' => $sec, 'columns' => [['id' => 'c', 'span' => 12, 'widgets' => [['id' => 'w', 'type' => 'heading', 'props' => ['text' => $text]]]]]]]];
    }

    private function page(string $slug, string $sec = 's1'): int
    {
        return (int) $this->postJson('/api/v1/builder/pages', ['title' => $slug, 'slug' => $slug, 'document' => $this->doc('x', $sec)])->assertCreated()->json('data.id');
    }

    public function test_revisions_autosave_publish_and_restore(): void
    {
        $id = (int) $this->postJson('/api/v1/builder/pages', ['title' => 'P', 'slug' => 'p', 'document' => $this->doc('one')])->assertCreated()->json('data.id');
        $this->patchJson("/api/v1/builder/pages/$id", ['document' => $this->doc('two')])->assertOk();
        $this->patchJson("/api/v1/builder/pages/$id", ['document' => $this->doc('auto-a'), 'autosave' => true])->assertOk();
        $this->patchJson("/api/v1/builder/pages/$id", ['document' => $this->doc('auto-b'), 'autosave' => true])->assertOk();
        $this->postJson("/api/v1/builder/pages/$id/publish")->assertOk();

        $list = $this->getJson("/api/v1/builder/revisions?target=page&id=$id")->assertOk()->json('data');
        $this->assertSame(['publish', 'autosave', 'save', 'save'], array_column($list, 'reason'));
        $first = end($list)['id'];
        $this->postJson("/api/v1/builder/revisions/$first/restore")->assertOk()
            ->assertJsonPath('data.document.sections.0.columns.0.widgets.0.props.text', 'one');
        $this->assertSame('restore', BuilderRevision::query()->orderByDesc('id')->value('reason'));
    }

    public function test_library_save_and_fetch(): void
    {
        $this->postJson('/api/v1/builder/library', ['kind' => 'section', 'title' => '<b>هیرو</b>', 'document' => ['version' => 2, 'sections' => [['id' => 's', 'columns' => []]]]])->assertCreated();
        $list = $this->getJson('/api/v1/builder/library?kind=section')->assertOk()->json('data');
        $this->assertSame('هیرو', $list[0]['title']);
        $this->getJson('/api/v1/builder/library/'.$list[0]['id'])->assertOk()->assertJsonPath('data.document.sections.0.id', 's');
        $this->deleteJson('/api/v1/builder/library/'.$list[0]['id'])->assertOk();
    }

    public function test_discard_slug_redirect_unpublish_schedule_and_trash(): void
    {
        $id = $this->page('about');
        $this->postJson("/api/v1/builder/pages/$id/discard")->assertUnprocessable();
        $this->postJson("/api/v1/builder/pages/$id/publish")->assertOk();
        $this->patchJson("/api/v1/builder/pages/$id", ['document' => $this->doc('changed', 'changed')])->assertOk();
        $this->postJson("/api/v1/builder/pages/$id/discard")->assertOk()->assertJsonPath('data.document.sections.0.id', 's1');

        $this->patchJson("/api/v1/builder/pages/$id", ['slug' => 'team'])->assertOk();
        $this->assertNotNull(SeoRedirect::query()->where('from_path', 'like', '%/about')->first());

        $this->postJson("/api/v1/builder/pages/$id/unpublish")->assertOk()->assertJsonPath('data.published', false);
        $this->putJson("/api/v1/builder/pages/$id/schedule", ['publish_at' => now()->subMinute()->toIso8601String()])->assertUnprocessable();
        $this->putJson("/api/v1/builder/pages/$id/schedule", ['publish_at' => now()->addMinutes(5)->toIso8601String(), 'unpublish_at' => now()->addHour()->toIso8601String()])->assertOk();
        $this->travel(6)->minutes();
        $this->artisan('builder:scheduled')->assertSuccessful();
        $this->assertTrue((bool) MarketingPage::find($id)->published);
        $this->travel(2)->hours();
        $this->artisan('builder:scheduled')->assertSuccessful();
        $this->assertFalse((bool) MarketingPage::find($id)->published);
        $this->travelBack();

        $this->deleteJson("/api/v1/builder/pages/$id?force=1")->assertUnprocessable();
        $this->deleteJson("/api/v1/builder/pages/$id")->assertOk();
        $this->assertSame('trash', MarketingPage::find($id)->status);
        $this->deleteJson("/api/v1/builder/pages/$id?force=1")->assertOk();
        $this->assertNull(MarketingPage::find($id));
    }

    public function test_preview_token_and_edit_lock(): void
    {
        $id = $this->page('preview-me');
        $token = $this->postJson("/api/v1/builder/pages/$id/preview-token")->assertOk()->json('data.token');
        $this->getJson('/api/v1/public/builder/preview/'.$token)->assertOk()->assertJsonPath('data.document.sections.0.id', 's1');
        $this->getJson('/api/v1/public/builder/preview/'.$id.'.'.str_repeat('a', 48))->assertNotFound();

        $this->postJson("/api/v1/builder/pages/$id/lock")->assertOk();
        $other = User::factory()->create();
        $other->assignRole('system_manager');
        $this->actingAs($other, 'sanctum');
        $this->postJson("/api/v1/builder/pages/$id/lock")->assertStatus(423);
        $this->patchJson("/api/v1/builder/pages/$id", ['title' => 'x'])->assertStatus(423);
        $this->postJson("/api/v1/builder/pages/$id/lock", ['takeover' => true])->assertOk();
        $this->patchJson("/api/v1/builder/pages/$id", ['title' => 'x'])->assertOk();
    }

    public function test_ab_test_and_events(): void
    {
        $a = $this->page('landing', 'A');
        $b = $this->page('landing-b', 'B');
        $this->postJson("/api/v1/builder/pages/$a/publish")->assertOk();
        $this->postJson("/api/v1/builder/pages/$b/publish")->assertOk();
        $this->putJson("/api/v1/builder/pages/$a/ab", ['variant_page_id' => $a, 'split' => 50, 'status' => 'running'])->assertUnprocessable();
        $test = $this->putJson("/api/v1/builder/pages/$a/ab", ['variant_page_id' => $b, 'split' => 30, 'status' => 'running'])->assertOk()->json('data');
        $this->postJson("/api/v1/public/builder/ab/{$test['id']}/event", ['variant' => 'b', 'type' => 'conversion'])->assertOk();
        $this->postJson("/api/v1/public/builder/ab/{$test['id']}/event", ['variant' => 'c', 'type' => 'view'])->assertUnprocessable();
        $this->getJson("/api/v1/builder/pages/$a/ab")->assertOk()->assertJsonPath('data.conversions_b', 1);
        $this->deleteJson("/api/v1/builder/pages/$a/ab")->assertOk();
    }

    public function test_collections_crud_and_public_listing(): void
    {
        $c = $this->postJson('/api/v1/builder/collections', ['name' => 'تیم', 'fields' => [
            ['key' => 'role', 'label' => 'سمت', 'type' => 'text'],
            ['key' => 'photo', 'label' => 'عکس', 'type' => 'image'],
        ]])->assertCreated()->json('data');
        $this->postJson("/api/v1/builder/collections/{$c['id']}/items", ['title' => 'علی', 'slug' => 'ali', 'data' => ['role' => '<b>مدیر</b>', 'photo' => 'javascript:alert(1)']])
            ->assertCreated()->assertJsonPath('data.data.role', 'مدیر')->assertJsonMissingPath('data.data.photo');
        $this->postJson("/api/v1/builder/collections/{$c['id']}/items", ['title' => 'Draft', 'published' => false])->assertCreated();
        $list = $this->getJson("/api/v1/public/builder/collections/{$c['slug']}/items")->assertOk()->json('data');
        $this->assertSame(1, $list['total']);
        $slug = $list['items'][0]['slug'];
        $this->getJson("/api/v1/public/builder/collections/{$c['slug']}/items/{$slug}")->assertOk()->assertJsonPath('data.item.title', 'علی');
        $this->getJson("/api/v1/public/builder/collections/{$c['slug']}/items/draft")->assertNotFound();
    }

    public function test_design_system_globals_are_normalized(): void
    {
        $s = $this->putJson('/api/v1/builder/globals', ['settings' => [
            'variables' => [['id' => 'brand', 'name' => 'برند', 'kind' => 'color', 'value' => '#e16ba6'], ['id' => 'bad', 'kind' => 'size', 'value' => '1px;}body{x:url(a)']],
            'classes' => [['id' => 'card', 'name' => '<b>Card</b>', 'style' => ['base' => ['radius' => '12px'], 'evil' => 1]]],
            'customCode' => [['id' => 'ga', 'name' => 'GA', 'location' => 'head', 'code' => '<script>1</script>', 'enabled' => true, 'paths' => "/shop*\njavascript:x"]],
        ]])->assertOk()->json('data.settings');
        $this->assertCount(1, $s['variables']);
        $this->assertSame('Card', $s['classes'][0]['name']);
        $this->assertSame('/shop*', $s['customCode'][0]['paths']);
    }

    public function test_kit_export_and_roles(): void
    {
        $this->getJson('/api/v1/builder/kit')->assertOk();
        $this->getJson('/api/v1/builder/roles')->assertOk();
    }
}

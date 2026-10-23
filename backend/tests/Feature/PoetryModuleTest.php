<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Core\Entities\SystemModule;
use Modules\Platform\Support\SiteTypeProfiles;
use Modules\Poetry\Entities\PoetryPoem;
use Modules\Poetry\Entities\PoetryTerm;
use Modules\Poetry\Services\PoetryImportParser;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class PoetryModuleTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        SystemModule::query()->updateOrCreate(['slug' => 'marketing'], ['name' => 'M', 'is_active' => true]);
    }

    private function term(string $kind, string $slug): int
    {
        return PoetryTerm::query()->where('kind', $kind)->where('slug', $slug)->value('id');
    }

    public function test_taxonomies_are_seeded_without_poems(): void
    {
        $this->assertSame(0, PoetryPoem::query()->count());
        $this->assertSame(6, PoetryTerm::query()->where('kind', 'occasion')->count());
        $this->assertSame(10, PoetryTerm::query()->where('kind', 'style')->count());
        foreach (['امام حسین', 'امام حسن مجتبی', 'حضرت قاسم', 'کاروان اسرا'] as $name) {
            $this->assertTrue(PoetryTerm::query()->where('kind', 'figure')->where('name', $name)->exists(), $name);
        }
        $this->getJson('/api/v1/public/poetry/terms?kind=occasion')->assertOk()->assertJsonCount(6, 'data');
        $this->assertArrayHasKey('poetry', SiteTypeProfiles::modulesFor('blog'));
    }

    public function test_admin_crud_public_detail_search_related_and_text(): void
    {
        Sanctum::actingAs($this->actingAsRole('system_manager'));
        $payload = [
            'title' => 'شب عاشورا', 'status' => 'published', 'occasion_id' => $this->term('occasion', 'muharram'),
            'term_ids' => [$this->term('figure', 'imam-hossein'), $this->term('style', 'noheh')],
            'pen_name' => 'طه', 'composed_label' => 'خرداد ۱۳۹۸', 'refrain_after_each' => true,
            'external_links' => [['label' => 'ایتا', 'url' => 'https://eitaa.com/x/1']],
            'stanzas' => [
                ['type' => 'refrain', 'repeat' => 2, 'lines' => ['حسین جان', 'ای جان جانان']],
                ['type' => 'stanza', 'lines' => ['مصراع اول بند يك', 'مصراع دوم بند']],
                ['type' => 'stanza', 'lines' => ['بند دوم سطر اول', 'بند دوم سطر دوم']],
            ],
        ];
        $id = $this->postJson('/api/v1/poetry/poems', $payload)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/poetry/poems', ['title' => 'دیگر', 'status' => 'published', 'term_ids' => [$this->term('figure', 'imam-hossein')], 'stanzas' => [['type' => 'stanza', 'lines' => ['x', 'y']]]])->assertCreated();
        $this->postJson('/api/v1/poetry/poems', ['title' => 'پیش‌نویس', 'stanzas' => [['type' => 'stanza', 'lines' => ['z']]]])->assertCreated();
        $this->postJson('/api/v1/poetry/poems', ['title' => 'بد', 'external_links' => [['url' => 'javascript:alert(1)']], 'stanzas' => [['type' => 'stanza', 'lines' => ['a']]]])->assertStatus(422);

        $slug = PoetryPoem::query()->find($id)->slug;
        $res = $this->getJson("/api/v1/public/poetry/poems/{$slug}")->assertOk();
        $res->assertJsonPath('data.pen_name', 'طه')->assertJsonPath('data.figures.0.slug', 'imam-hossein')->assertJsonPath('data.view_count', 1);
        // refrain auto-inserted after each stanza: refrain, s1, refrain, s2, refrain
        $this->assertSame(['refrain', 'stanza', 'refrain', 'stanza', 'refrain'], array_column($res->json('data.sequence'), 'type'));
        $this->assertSame('دیگر', $res->json('data.related.0.title'));

        // verse-line search with Arabic ي/ك normalised
        $this->getJson('/api/v1/public/poetry/poems?q='.urlencode('بند یک'))->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/public/poetry/poems?figure=imam-hossein')->assertJsonPath('meta.total', 2);
        $this->getJson('/api/v1/public/poetry/poems?style=noheh&occasion=muharram')->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/public/poetry/poems')->assertJsonPath('meta.total', 2); // draft hidden
        $this->getJson('/api/v1/public/poetry/figures/imam-hossein')->assertOk()->assertJsonPath('data.figure.name', 'امام حسین')->assertJsonPath('meta.total', 2);
        $this->getJson('/api/v1/public/poetry/popular')->assertOk()->assertJsonPath('data.0.slug', $slug);

        $text = $this->get("/api/v1/public/poetry/poems/{$slug}/text")->assertOk();
        $this->assertStringContainsString('attachment', $text->headers->get('Content-Disposition'));
        $this->assertSame(3, substr_count($text->getContent(), 'حسین جان'));

        $this->putJson("/api/v1/poetry/poems/{$id}", ['refrain_after_each' => false])->assertOk();
        $this->assertCount(3, $this->getJson("/api/v1/public/poetry/poems/{$slug}")->json('data.sequence'));
    }

    public function test_admin_requires_auth(): void
    {
        $this->getJson('/api/v1/poetry/poems')->assertUnauthorized();
        $this->postJson('/api/v1/poetry/import/parse', ['text' => 'x'])->assertUnauthorized();
    }

    public function test_import_parser_handles_telegram_format(): void
    {
        $raw = <<<'TXT'
═══ ✾ ═══
🏴 نوحه زنجیرزنی حماسی
#جدید
خوانده نشده
تیتر: ما ادری
تیتر
یا حسین یا حسین
...
برگردان:
ای کربلا ای کربلا
عشق منی ای کربلا x2
...
بند اول مصراع یک
بند اول مصراع دو
...
بند دوم مصراع یک
بند دوم مصراع دو
...
#طه
خرداد ۱۳۹۸
🆔 @ali_khabari_poet
TXT;
        $r = (new PoetryImportParser)->parse($raw);
        $this->assertSame('نوحه زنجیرزنی حماسی', $r['style_label']);
        $this->assertSame('تیتر: ما ادری', $r['melody_credit']);
        $this->assertTrue($r['flags']['is_new']);
        $this->assertTrue($r['flags']['not_performed']);
        $this->assertSame('طه', $r['pen_name']);
        $this->assertSame('خرداد ۱۳۹۸', $r['composed_label']);
        $this->assertSame(['title', 'refrain', 'stanza', 'stanza'], array_column($r['stanzas'], 'type'));
        $this->assertSame(2, $r['stanzas'][1]['repeat']);
        $this->assertSame(['ای کربلا ای کربلا', 'عشق منی ای کربلا'], $r['stanzas'][1]['lines']);
        $this->assertSame('یا حسین یا حسین', $r['title']);
        $this->assertStringNotContainsString('ali_khabari', json_encode($r, JSON_UNESCAPED_UNICODE));
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Modules\AiContent\Entities\AiSetting;
use Modules\Core\Entities\SystemModule;
use Modules\Marketing\Entities\MarketingAcademyCourse;
use Modules\Marketing\Entities\MarketingAcademyLesson;
use Modules\Marketing\Entities\MarketingPage;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class AcademyStructureAndPageSeoTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        foreach (['ai_content', 'marketing'] as $slug) {
            SystemModule::query()->updateOrCreate(['slug' => $slug], ['name' => $slug, 'is_active' => true]);
        }
        Sanctum::actingAs($this->actingAsRole('system_manager'));
    }

    public function test_ai_structure_is_a_suggestion_and_save_creates_unpublished_grouped_lessons(): void
    {
        AiSetting::query()->updateOrCreate(['key' => 'main'], ['value' => ['default_provider' => 'openai', 'openai_api_key' => 'sk-test']]);
        Http::fake(['*/chat/completions' => Http::response(['choices' => [['message' => ['content' => '{"description":"توضیح","chapters":[{"title":"مبانی","lessons":[{"title":"درس یک"},{"title":"درس دو"}]}]}']]]])]);
        $course = MarketingAcademyCourse::query()->create(['slug' => 'c', 'title' => 'سئو', 'published' => true]);

        $this->postJson('/api/v1/ai-content/content-assist', ['action' => 'course_structure', 'surface' => 'academy.structure', 'target_id' => $course->id, 'context' => ['course_title' => 'سئو']])
            ->assertOk()->assertJsonPath('data.structured.chapters.0.lessons.1.title', 'درس دو')->assertJsonPath('data.suggestion', 'توضیح');
        $this->assertSame(0, MarketingAcademyLesson::query()->count());

        $this->putJson("/api/v1/marketing/academy/{$course->id}/structure", ['chapters' => [
            ['title' => 'مبانی', 'summary' => 's', 'lessons' => [['title' => 'Lesson One', 'content' => '<p>x</p>'], ['title' => 'Lesson Two']]],
        ]])->assertOk()->assertJsonCount(1, 'data.chapters')->assertJsonCount(2, 'data.chapters.0.lessons');
        $this->assertSame(0, MarketingAcademyLesson::query()->where('published', true)->count());
        $this->assertSame('lesson-one', MarketingAcademyLesson::query()->orderBy('sort_order')->value('slug'));

        MarketingAcademyLesson::query()->update(['published' => true]);
        $this->getJson('/api/v1/public/academy/c')->assertOk()->assertJsonCount(1, 'data.chapters')->assertJsonPath('data.lessons.0.chapter_id', fn ($v) => $v !== null);
    }

    public function test_builder_page_seo_saved_in_meta_and_public(): void
    {
        $page = MarketingPage::query()->create(['slug' => 'x', 'title_fa' => 'X', 'published' => true, 'status' => 'published', 'meta' => ['keep' => 1],
            'builder_published' => ['version' => 1, 'sections' => []], 'builder_draft' => ['version' => 1, 'sections' => []]]);
        $this->patchJson("/api/v1/builder/pages/{$page->id}", ['seo' => ['title' => 'عنوان سئو', 'description' => 'توضیح', 'noindex' => false]])
            ->assertOk()->assertJsonPath('data.seo.title', 'عنوان سئو');
        $this->assertSame(1, $page->fresh()->meta['keep']);
        $this->getJson('/api/v1/public/builder/pages/x')->assertOk()->assertJsonPath('data.seo.description', 'توضیح');
    }
}

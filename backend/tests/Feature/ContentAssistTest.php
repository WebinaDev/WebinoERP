<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Modules\AiContent\Entities\AiJob;
use Modules\AiContent\Entities\AiSetting;
use Modules\Core\Entities\SystemModule;
use Modules\Marketing\Entities\MarketingBlogPost;
use Tests\Concerns\SeedsRbac;
use Tests\TestCase;

class ContentAssistTest extends TestCase
{
    use RefreshDatabase;
    use SeedsRbac;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRbac();
        SystemModule::query()->updateOrCreate(['slug' => 'ai_content'], ['name' => 'AI', 'is_active' => true]);
        Sanctum::actingAs($this->actingAsRole('system_manager'));
    }

    private function mockProvider(string $content): void
    {
        AiSetting::query()->updateOrCreate(['key' => 'main'], ['value' => ['default_provider' => 'openai', 'openai_api_key' => 'sk-test', 'openai_model' => 'gpt-4o-mini', 'usd_to_toman' => 1000000]]);
        Http::fake(['*/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => $content]]],
            'usage' => ['prompt_tokens' => 1000, 'completion_tokens' => 2000],
        ])]);
    }

    public function test_rewrite_uses_provider_logs_job_with_cost_and_never_writes_content(): void
    {
        $this->mockProvider('«متنی روان‌تر»');
        $post = MarketingBlogPost::query()->create(['slug' => 'p', 'title' => 'T', 'body' => 'متن اصلی', 'status' => 'draft']);

        $this->postJson('/api/v1/ai-content/content-assist', ['action' => 'rewrite', 'surface' => 'blog', 'field' => 'body', 'text' => 'متن اصلی', 'target_id' => $post->id])
            ->assertOk()->assertJsonPath('data.suggestion', 'متنی روان‌تر')->assertJsonPath('data.source', 'provider');

        $job = AiJob::query()->where('job_type', 'content_assist')->firstOrFail();
        $this->assertSame('done', $job->status);
        $this->assertSame('openai', $job->provider);
        $this->assertSame(2000, $job->tokens_out);
        $this->assertEqualsWithDelta(1350.0, $job->cost_toman, 0.01); // (1000*0.15 + 2000*0.6) USD per 1M tokens * 1,000,000 toman
        $this->assertSame('blog.body', $job->target_type);
        $this->assertSame('متن اصلی', $post->fresh()->body);
        $this->assertSame('draft', $post->fresh()->status);
        Http::assertSent(fn ($r) => str_contains($r['messages'][0]['content'], 'نیم‌فاصله') && str_contains($r['messages'][1]['content'], 'متن اصلی'));

        $this->getJson('/api/v1/ai-content/content-assist/history?surface=blog')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_structured_page_draft_and_faq(): void
    {
        $this->mockProvider('```json {"title":"خدمات","sections":[{"title":"هیرو","text":"متن"},{"title":"مزایا","text":"..."}]} ```');
        $this->postJson('/api/v1/ai-content/content-assist', ['action' => 'page_draft', 'surface' => 'builder', 'text' => 'صفحه خدمات طراحی سایت'])
            ->assertOk()->assertJsonCount(2, 'data.structured.sections')->assertJsonPath('data.structured.sections.0.title', 'هیرو');
    }

    public function test_without_provider_local_actions_work_and_generative_ones_report_503(): void
    {
        Http::fake();
        $this->postJson('/api/v1/ai-content/content-assist', ['action' => 'slug', 'surface' => 'blog', 'text' => 'Website Design Guide 2026'])
            ->assertOk()->assertJsonPath('data.suggestion', 'website-design-guide-2026')->assertJsonPath('data.source', 'local');
        $this->postJson('/api/v1/ai-content/content-assist', ['action' => 'read_time', 'surface' => 'blog', 'text' => str_repeat('واژه ', 401)])
            ->assertOk()->assertJsonPath('data.suggestion', '3');
        $this->postJson('/api/v1/ai-content/content-assist', ['action' => 'generate', 'surface' => 'blog', 'field' => 'body'])->assertStatus(503);
        $this->assertSame('failed', AiJob::query()->latest('id')->value('status'));
        Http::assertNothingSent();
        $this->postJson('/api/v1/ai-content/content-assist', ['action' => 'hack', 'surface' => 'blog'])->assertUnprocessable();
    }
}

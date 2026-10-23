<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Modules\Crm\Entities\CrmLead;
use Modules\Marketing\Database\Seeders\MarketingFormsSeeder;
use Modules\Marketing\Entities\MarketingPage;
use Modules\Marketing\Services\Builder\FormWidgetActions;
use Tests\TestCase;

class MarketingFormWidgetActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seed(MarketingFormsSeeder::class);
        Http::fake(['*' => Http::response(['ok' => true])]);
    }

    private function page(array $props): void
    {
        $doc = ['sections' => [['id' => 's1', 'columns' => [['id' => 'c1', 'span' => 12, 'widgets' => [
            ['id' => 'wform1', 'type' => 'form', 'props' => $props + ['formKey' => 'contact']],
        ]]]]]];
        MarketingPage::query()->create(['slug' => 'contact-us', 'title_fa' => 'تماس', 'status' => 'published', 'published' => true, 'builder_published' => $doc]);
    }

    private function submit(array $extra = [])
    {
        $res = $this->postJson('/api/v1/public/forms/contact', $extra + ['payload' => ['name' => 'علی خبری', 'phone' => '09120000000', 'message' => 'سلام'], 'widget_id' => 'wform1']);
        $this->app->terminate();

        return $res;
    }

    public function test_captcha_is_enforced_from_the_published_widget(): void
    {
        $this->page(['captcha' => true]);
        $this->submit()->assertStatus(422);
        $this->submit(['_captcha' => '1'])->assertCreated();
    }

    public function test_crm_action_creates_a_lead(): void
    {
        $this->page(['crmAction' => 'lead']);
        $before = CrmLead::query()->count();
        $this->submit()->assertCreated();
        $this->assertSame($before + 1, CrmLead::query()->count());
    }

    public function test_webhook_is_sent_from_the_server_to_a_public_url(): void
    {
        $this->page(['webhookUrl' => 'http://93.184.216.34/hook']);
        $this->submit()->assertCreated();
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'http://93.184.216.34/hook' && $r['form_key'] === 'contact');
    }

    public function test_private_and_request_supplied_webhooks_are_ignored(): void
    {
        $this->page(['webhookUrl' => 'http://169.254.169.254/latest/meta-data']);
        $this->submit(['webhook_url' => 'https://93.184.216.34/evil', 'crm_action' => 'lead'])->assertCreated();
        Http::assertNothingSent();
    }

    public function test_webhook_target_guard(): void
    {
        $this->assertNull(FormWidgetActions::webhookTarget('ftp://93.184.216.34/x'));
        $this->assertNull(FormWidgetActions::webhookTarget('http://127.0.0.1/x'));
        $this->assertNull(FormWidgetActions::webhookTarget('http://10.0.0.5/x'));
        $this->assertNull(FormWidgetActions::webhookTarget('http://localhost/x'));
        $this->assertNull(FormWidgetActions::webhookTarget('http://user:pw@93.184.216.34/x'));
        $this->assertNotNull(FormWidgetActions::webhookTarget('https://93.184.216.34/x'));
    }

    public function test_unknown_widget_changes_nothing(): void
    {
        $this->postJson('/api/v1/public/forms/contact', ['payload' => ['name' => 'x', 'phone' => '0912', 'message' => 'سلام'], 'widget_id' => 'nope'])->assertCreated();
        Http::assertNothingSent();
    }
}

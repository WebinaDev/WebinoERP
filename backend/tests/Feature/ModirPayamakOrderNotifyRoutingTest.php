<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Integrations\Entities\ModirPayamakPatternRegistry;
use Modules\Integrations\Services\ModirPayamakEdgeClient;
use Modules\Integrations\Services\ModirPayamakSiteSmsService;
use Tests\TestCase;

class ModirPayamakOrderNotifyRoutingTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{code: string, to: string}> */
    public array $sent = [];

    private string $domain = 'shop-route.example.com';

    protected function setUp(): void
    {
        parent::setUp();
        $test = $this;
        $this->app->instance(ModirPayamakEdgeClient::class, new class($test) extends ModirPayamakEdgeClient
        {
            public function __construct(private $t) {}

            public function isConfigured(): bool
            {
                return true;
            }

            public function defaultFrom(): string
            {
                return '1000';
            }

            public function sendPattern(string $from, string $code, array $recipients, array $params): array
            {
                foreach ($recipients as $r) {
                    $this->t->sent[] = ['code' => $code, 'to' => $r];
                }

                return ['ok' => true, 'data' => ['id' => 1]];
            }
        });

        foreach (['processing', 'completed', 'on-hold'] as $ev) {
            foreach (['order_customer' => 'C', 'order_admin' => 'A'] as $scope => $p) {
                ModirPayamakPatternRegistry::query()->create([
                    'domain' => $this->domain, 'scope' => $scope, 'event_key' => $ev,
                    'ippanel_code' => $p.'-'.$ev, 'sync_status' => 'synced',
                ]);
            }
        }
        app(ModirPayamakSiteSmsService::class)->saveShopSettings($this->domain, [
            'admin_phones' => ['09121111111', '+989122222222'],
            'events' => [
                'processing' => ['customer' => true, 'admin' => true],
                'completed' => ['customer' => true, 'admin' => false],
                'on-hold' => ['customer' => false, 'admin' => true],
            ],
        ]);
    }

    private function field($res, string $path)
    {
        return data_get($res->json(), 'data.'.$path, data_get($res->json(), $path));
    }

    private function notify(string $event, array $extra = [])
    {
        return $this->postJson('/api/webinocrm/v1/modirpayamak/orders/notify', array_merge([
            'domain' => $this->domain,
            'event_key' => $event,
            'order' => ['order_id' => 5, 'customer_phone' => '09351234567'],
        ], $extra));
    }

    public function test_customer_only_status_goes_to_customer_with_customer_pattern(): void
    {
        $this->assertTrue($this->field($this->notify('completed')->assertOk(), 'ok'));
        $this->assertSame([['code' => 'C-completed', 'to' => '09351234567']], $this->sent);
    }

    public function test_admin_only_status_goes_to_admins_with_admin_pattern(): void
    {
        $this->notify('on-hold')->assertOk();
        $this->assertSame([
            ['code' => 'A-on-hold', 'to' => '09121111111'],
            ['code' => 'A-on-hold', 'to' => '09122222222'],
        ], $this->sent);
    }

    public function test_both_roles(): void
    {
        $this->notify('processing')->assertOk();
        $this->assertCount(3, $this->sent);
        $this->assertSame('C-processing', $this->sent[0]['code']);
    }

    public function test_missing_customer_phone_never_falls_back_to_admin(): void
    {
        $res = $this->notify('completed', ['order' => ['order_id' => 5]]);
        $this->assertSame('no_customer_phone', $this->field($res->assertOk(), 'results.customer.reason'));
        $this->assertSame([], $this->sent);
    }

    public function test_recipients_object_from_new_plugin_is_not_treated_as_phone_list(): void
    {
        $this->notify('completed', ['recipients' => ['customer' => true, 'admin' => false, 'customer_phone' => '09359999999'], 'order' => []])
            ->assertOk();
        $this->assertSame([['code' => 'C-completed', 'to' => '09359999999']], $this->sent);
    }

    public function test_erp_settings_win_over_plugin_hint(): void
    {
        $this->notify('completed', ['recipients' => ['customer' => false, 'admin' => true, 'customer_phone' => '']])->assertOk();
        $this->assertSame([['code' => 'C-completed', 'to' => '09351234567']], $this->sent);
    }

    public function test_legacy_phone_list_still_works(): void
    {
        $this->notify('completed', ['recipients' => ['09127777777']])->assertOk();
        $this->assertSame([['code' => 'C-completed', 'to' => '09127777777']], $this->sent);
    }

    public function test_event_off_sends_nothing(): void
    {
        app(ModirPayamakSiteSmsService::class)->saveShopSettings($this->domain, ['events' => ['completed' => ['customer' => false, 'admin' => false]]]);
        $this->assertSame('event_off', $this->field($this->notify('completed')->assertOk(), 'reason'));
        $this->assertSame([], $this->sent);
    }
}

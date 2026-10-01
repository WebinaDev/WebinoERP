<?php

namespace Tests\Unit;

use Modules\Platform\Services\LocalSameVpsProvisioner;
use Modules\Platform\Support\TenantSiteStack;
use Modules\SiteBuilder\Services\LicenseProvisionerService;
use PHPUnit\Framework\TestCase;

class TenantFrontendBackendProbeTest extends TestCase
{
    public function test_probe_urls_prefer_unique_service_then_private_alias(): void
    {
        $this->assertSame('ws-bluecafe-backend', TenantSiteStack::backendService('bluecafe'));
        $this->assertSame([
            'http://ws-bluecafe-backend:8080/api/v1/health/metrics',
            'http://backend:8080/api/v1/health/metrics',
        ], TenantSiteStack::frontendBackendProbeUrls('bluecafe'));
    }

    public function test_compose_keeps_private_net_alias_for_the_app(): void
    {
        $yaml = TenantSiteStack::composeYaml('bluecafe');

        $this->assertStringContainsString('INTERNAL_API_URL: http://backend:8080', $yaml);
        $this->assertStringContainsString('API_PROXY_TARGET: http://backend:8080', $yaml);
        $this->assertSame(1, substr_count($yaml, 'aliases: [backend]'));

        $backend = $this->serviceBlock($yaml, 'ws-bluecafe-backend');
        $this->assertMatchesRegularExpression(
            '/ws-bluecafe_net:\n\s+aliases: \[backend\]\n\s+proxy:\s*$/',
            $backend,
        );
        $this->assertDoesNotMatchRegularExpression('/proxy:\s*\n\s+aliases:/', $backend);

        $caddy = TenantSiteStack::caddySnippet('bluecafe.example', 'bluecafe');
        $this->assertStringContainsString('reverse_proxy ws-bluecafe-backend:8080', $caddy);
    }

    public function test_unique_hostname_success_does_not_fall_back(): void
    {
        $probe = new FrontendBackendProbeDouble;
        $probe->script = [
            ['status' => 200, 'body' => '{"data":{"ok":true}}'],
        ];

        $result = $probe->runProbe('ws-bluecafe-frontend', 'bluecafe', microtime(true) + 30);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['attempted']);
        $this->assertSame([
            'http://ws-bluecafe-backend:8080/api/v1/health/metrics',
        ], $probe->urls);
        $this->assertStringContainsString('http://ws-bluecafe-backend:8080/api/v1/health/metrics: ok ', $result['lines'][0]);
        $this->assertStringNotContainsString('internal alias fallback', implode("\n", $result['lines']));
    }

    public function test_alias_fallback_passes_when_unique_name_fails(): void
    {
        $probe = new FrontendBackendProbeDouble;
        $probe->script = [
            ['status' => 0, 'body' => 'getaddrinfo EAI_AGAIN backend'],
            ['status' => 200, 'body' => '{"data":{"status":"ok"}}'],
        ];

        $result = $probe->runProbe('ws-bluecafe-frontend', 'bluecafe', microtime(true) + 30);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['attempted']);
        $this->assertSame([
            'http://ws-bluecafe-backend:8080/api/v1/health/metrics',
            'http://backend:8080/api/v1/health/metrics',
        ], $probe->urls);
        $this->assertStringContainsString('FAIL', $result['lines'][0]);
        $this->assertStringContainsString('internal alias fallback', $result['lines'][1]);
        $this->assertStringContainsString(': ok ', $result['lines'][1]);
    }

    public function test_both_names_failing_stays_a_real_failure(): void
    {
        $probe = new FrontendBackendProbeDouble;
        $probe->script = [
            ['status' => 404, 'body' => 'not found'],
            ['status' => 200, 'body' => '<html>no metrics</html>'],
        ];

        $result = $probe->runProbe('ws-bluecafe-frontend', 'bluecafe', microtime(true) + 30);

        $this->assertFalse($result['ok']);
        $this->assertTrue($result['attempted']);
        $this->assertCount(2, $probe->urls);
        $this->assertStringContainsString('FAIL', $result['lines'][0]);
        $this->assertStringContainsString('FAIL', $result['lines'][1]);
    }

    public function test_fallback_is_skipped_when_budget_runs_out_after_primary(): void
    {
        $probe = new FrontendBackendProbeDouble;
        $probe->budgetAfter = 1;
        $probe->script = [
            ['status' => 0, 'body' => ''],
        ];

        $result = $probe->runProbe('ws-bluecafe-frontend', 'bluecafe', microtime(true) + 30);

        $this->assertFalse($result['ok']);
        $this->assertTrue($result['attempted']);
        $this->assertSame([
            'http://ws-bluecafe-backend:8080/api/v1/health/metrics',
        ], $probe->urls);
        $this->assertStringContainsString('FAIL', $result['lines'][0]);
        $this->assertStringContainsString('http://backend:8080/api/v1/health/metrics (internal alias fallback): skipped (budget)', $result['lines'][1]);
    }

    public function test_no_attempt_when_budget_is_exhausted(): void
    {
        $probe = new FrontendBackendProbeDouble;
        $result = $probe->runProbe('ws-bluecafe-frontend', 'bluecafe', microtime(true) - 5);

        $this->assertFalse($result['ok']);
        $this->assertFalse($result['attempted']);
        $this->assertSame([], $probe->urls);
        $this->assertStringContainsString(
            'frontend→http://ws-bluecafe-backend:8080/api/v1/health/metrics: skipped (budget)',
            $result['lines'][0],
        );
    }

    private function serviceBlock(string $yaml, string $service): string
    {
        $needle = '  '.$service.":\n";
        $start = strpos($yaml, $needle);
        $this->assertNotFalse($start);
        $from = substr($yaml, $start + strlen($needle));
        $this->assertSame(1, preg_match('/\n  (?=\S)/', $from, $match, PREG_OFFSET_CAPTURE));

        return substr($from, 0, $match[0][1]);
    }
}

/**
 * @internal
 */
final class FrontendBackendProbeDouble extends LocalSameVpsProvisioner
{
    /** @var list<string> */
    public array $urls = [];

    /** @var list<array{status:int,body:string}> */
    public array $script = [];

    /** Return a real budget this many times, then pretend time is gone. */
    public ?int $budgetAfter = null;

    private int $budgetCalls = 0;

    public function __construct()
    {
        parent::__construct(new LicenseProvisionerService);
    }

    protected function probeHttp(string $container, string $url, int $timeout = 20): array
    {
        $this->urls[] = $url;

        return array_shift($this->script) ?? ['status' => 0, 'body' => ''];
    }

    protected function budgetLeft(float $deadline, int $minSeconds = 2): ?int
    {
        $this->budgetCalls++;
        if ($this->budgetAfter !== null && $this->budgetCalls > $this->budgetAfter) {
            return null;
        }

        return parent::budgetLeft($deadline, $minSeconds);
    }

    /**
     * @return array{ok:bool,attempted:bool,lines:list<string>}
     */
    public function runProbe(string $container, string $slug, float $deadline): array
    {
        return $this->probeFrontendToBackend($container, $slug, $deadline);
    }
}
